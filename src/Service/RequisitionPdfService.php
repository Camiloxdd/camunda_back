<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

class RequisitionPdfService
{
    private Connection $conn;
    private LoggerInterface $logger;
    private string $tempDir;
    private string $templatePath;

    public function __construct(Connection $connection, LoggerInterface $logger, string $projectDir)
    {
        $this->conn = $connection;
        $this->logger = $logger;
        $this->tempDir = $projectDir . '/var/temp';
        $this->templatePath = $projectDir . '/templates/plantilla.xlsx';

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    public function generatePdf(int $id): string
    {
        try {
            // 1️⃣ Fetch requisición
            $requisicion = $this->conn->fetchAssociative(
                'SELECT * FROM requisiciones WHERE id = ?',
                [$id]
            );

            if (!$requisicion) {
                throw new \Exception('Requisición no encontrada');
            }

            // 2️⃣ Fetch productos
            $productos = $this->conn->fetchAllAssociative(
                'SELECT * FROM requisicion_productos WHERE requisicion_id = ?',
                [$id]
            );

            $this->logger->info("📦 Productos encontrados: " . count($productos));

            // 3️⃣ Load Excel template
            $spreadsheet = IOFactory::load($this->templatePath);
            $worksheet = $spreadsheet->getSheetByName('F-SGA-SG-19');

            // 4️⃣ Fill header (cabecera general)
            $this->fillHeader($worksheet, $requisicion);

            // 5️⃣ Fill products
            $this->fillProducts($worksheet, $productos, $requisicion);

            // 6️⃣ Fill approvers
            $this->fillApprovers($worksheet, $requisicion, $productos);

            // 7️⃣ Save Excel temp file
            $excelTemp = $this->tempDir . '/requisicion_' . $id . '.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($excelTemp);

            $this->logger->info("✅ Excel guardado en: " . $excelTemp);

            // 8️⃣ Convert Excel to PDF using LibreOffice (mejor que mPDF para Excel)
            $pdfTemp = $this->tempDir . '/requisicion_' . $id . '.pdf';
            $this->convertExcelToPdfWithLibreOffice($excelTemp, $pdfTemp);

            return $pdfTemp;
        } catch (\Throwable $e) {
            $this->logger->error('Error generating PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fillHeader($worksheet, $requisicion): void
    {
        $worksheet->getCell('E7')->setValue($requisicion['nombre_solicitante'] ?? 'N/A');
        $worksheet->getCell('E8')->setValue($requisicion['fecha'] ?? 'N/A');
        $worksheet->getCell('E9')->setValue($requisicion['fecha_requerido_entrega'] ?? 'N/A');
        $worksheet->getCell('E10')->setValue($requisicion['justificacion'] ?? 'N/A');
        $worksheet->getCell('O7')->setValue($requisicion['area'] ?? 'N/A');
        $worksheet->getCell('O8')->setValue($requisicion['sede'] ?? 'N/A');
        $worksheet->getCell('K9')->setValue($requisicion['urgencia'] ?? 'N/A');
        $worksheet->getCell('T10')->setValue($requisicion['presupuestada'] ? 'Sí' : 'No');
        $worksheet->getCell('T9')->setValue($requisicion['tiempoAproximadoGestion'] ?? 'N/A');
    }

    private function fillProducts($worksheet, $productos, $requisicion): void
    {
        $startRow = 14;
        foreach ($productos as $idx => $item) {
            $row = $startRow + $idx;
            $worksheet->getCell('B' . $row)->setValue($idx + 1);
            $worksheet->getCell('C' . $row)->setValue($item['nombre'] ?? 'N/A');
            $worksheet->getCell('F' . $row)->setValue((int)($item['cantidad'] ?? 0));
            $worksheet->getCell('G' . $row)->setValue($item['centro_costo'] ?? 'N/A');
            $worksheet->getCell('H' . $row)->setValue($item['cuenta_contable'] ?? 'N/A');
            $worksheet->getCell('L' . $row)->setValue($this->parseCurrency($item['valor_estimado'] ?? 0));
            $worksheet->getCell('J' . $row)->setValue($requisicion['presupuestada'] ? 'Sí' : 'No');
            $worksheet->getCell('M' . $row)->setValue($item['descripcion'] ?? 'N/A');
            $worksheet->getCell('N' . $row)->setValue($item['compra_tecnologica'] ? 'Sí Aplica' : 'No Aplica');
            $worksheet->getCell('R' . $row)->setValue($item['ergonomico'] ? 'Sí Aplica' : 'No Aplica');
        }
    }

    private function fillApprovers($worksheet, $requisicion, $productos): void
    {
        try {
            // 1️⃣ Get solicitante area
            $userRow = $this->conn->fetchAssociative(
                'SELECT area FROM user WHERE nombre = ? LIMIT 1',
                [$requisicion['nombre_solicitante']]
            );
            $solicitanteArea = strtoupper(trim($userRow['area'] ?? $requisicion['area'] ?? ''));

            $this->logger->info("📌 Área solicitante: " . $solicitanteArea);

            // 2️⃣ Detect flags
            $hasTecnologico = $this->arrayHasFlag($productos, 'compra_tecnologica');
            $hasErgonomico = $this->arrayHasFlag($productos, 'ergonomico');

            // 3️⃣ Determine required roles
            $rolesNeeded = [];
            if (strpos($solicitanteArea, 'SST') !== false) {
                $rolesNeeded[] = 'dicSST';
                if ($hasTecnologico && $hasErgonomico) {
                    $rolesNeeded = array_merge($rolesNeeded, ['dicTYP', 'gerSST', 'gerTyC']);
                } elseif ($hasTecnologico) {
                    $rolesNeeded[] = 'gerTyC';
                } elseif ($hasErgonomico) {
                    $rolesNeeded[] = 'gerSST';
                }
            } elseif (strpos($solicitanteArea, 'TYP') !== false) {
                $rolesNeeded[] = 'dicTYP';
                if ($hasTecnologico && $hasErgonomico) {
                    $rolesNeeded = array_merge($rolesNeeded, ['dicSST', 'gerTyC']);
                } elseif ($hasTecnologico) {
                    $rolesNeeded[] = 'gerTyC';
                } elseif ($hasErgonomico) {
                    $rolesNeeded[] = 'gerSST';
                }
            }

            $rolesNeeded = array_unique($rolesNeeded);
            $this->logger->info("✅ Roles requeridos: " . json_encode($rolesNeeded));

            // 4️⃣ Fetch users by roles
            $usuariosPorCargo = [];
            if (!empty($rolesNeeded)) {
                $placeholders = implode(',', array_fill(0, count($rolesNeeded), '?'));
                $usuarios = $this->conn->fetchAllAssociative(
                    "SELECT nombre, cargo FROM user WHERE cargo IN ($placeholders)",
                    $rolesNeeded
                );
                foreach ($usuarios as $u) {
                    $usuariosPorCargo[$u['cargo']] = $u['nombre'];
                }
            }

            $this->logger->info("👤 Usuarios encontrados: " . json_encode($usuariosPorCargo));

            // 5️⃣ Clear cells
            $nameCells = ['D28', 'I28', 'M28', 'O28', 'S28'];
            $sigCells = ['D29', 'I29', 'M29', 'O29', 'S29'];
            foreach (array_merge($nameCells, $sigCells) as $cell) {
                $worksheet->getCell($cell)->setValue('');
            }

            // 6️⃣ Write solicitante
            $worksheet->getCell('D28')->setValue($requisicion['nombre_solicitante'] ?? 'N/A');

            // 7️⃣ Write directors
            $worksheet->getCell('I28')->setValue($usuariosPorCargo['dicTYP'] ?? 'N/A');
            $worksheet->getCell('M28')->setValue($usuariosPorCargo['dicSST'] ?? 'N/A');

            // 8️⃣ Write managers
            $worksheet->getCell('O28')->setValue($usuariosPorCargo['gerTyC'] ?? 'N/A');
            $worksheet->getCell('S28')->setValue($usuariosPorCargo['gerSST'] ?? 'N/A');

            // 9️⃣ Check administrative and general management
            $SMLV = 1300000;
            $limite = $SMLV * 10;
            $valorTotal = (float)($requisicion['valor_total'] ?? 0);

            if (!$requisicion['presupuestada'] && $valorTotal >= $limite) {
                $rolesAdmin = ['gerAdmin', 'gerGeneral'];
                $missingRoles = array_diff($rolesAdmin, array_keys($usuariosPorCargo));
                if (!empty($missingRoles)) {
                    $placeholders = implode(',', array_fill(0, count($missingRoles), '?'));
                    $admins = $this->conn->fetchAllAssociative(
                        "SELECT nombre, cargo FROM user WHERE cargo IN ($placeholders)",
                        $missingRoles
                    );
                    foreach ($admins as $u) {
                        $usuariosPorCargo[$u['cargo']] = $u['nombre'];
                    }
                }
                $worksheet->getCell('D39')->setValue($usuariosPorCargo['gerAdmin'] ?? 'N/A');
                $worksheet->getCell('M39')->setValue($usuariosPorCargo['gerGeneral'] ?? 'N/A');
            }
        } catch (\Throwable $e) {
            $this->logger->warning("⚠️ Error calculando aprobaciones: " . $e->getMessage());
        }
    }

    private function convertExcelToPdfWithLibreOffice(string $excelPath, string $pdfPath): void
    {
        // Usar LibreOffice para convertir Excel a PDF (mejor calidad)
        $command = sprintf(
            'libreoffice --headless --convert-to pdf --outdir %s %s',
            escapeshellarg(dirname($pdfPath)),
            escapeshellarg($excelPath)
        );

        $this->logger->info("Ejecutando comando: " . $command);
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error("LibreOffice error: " . implode("\n", $output));
            throw new \Exception('Error converting Excel to PDF with LibreOffice. Code: ' . $returnCode);
        }

        if (!file_exists($pdfPath)) {
            throw new \Exception('PDF file was not created. Check LibreOffice installation.');
        }

        $this->logger->info("✅ PDF creado correctamente en: " . $pdfPath);
    }

    private function parseCurrency($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $str = trim((string)$value);
        return (float)preg_replace('/[^\d.-]/', '', $str);
    }

    private function arrayHasFlag(array $items, string $key): bool
    {
        foreach ($items as $item) {
            if (!empty($item[$key])) {
                return true;
            }
        }
        return false;
    }
}

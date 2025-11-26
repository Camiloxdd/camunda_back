<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Psr\Log\LoggerInterface;
use ConvertApi\ConvertApi;

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
        $this->templatePath = $projectDir . '/templates/plantillaa.xlsx';

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Set API Key
        ConvertApi::setApiCredentials($_ENV['CONVERT_API_SECRET'] ?? '');
    }

        public function generatePdf(int $id): string
    {
        try {

            // 1) Traer datos
            $requisicion = $this->conn->fetchAssociative(
                'SELECT * FROM requisiciones WHERE id = ?',
                [$id]
            );
            if (!$requisicion) {
                throw new \Exception('Requisición no encontrada');
            }

            $productos = $this->conn->fetchAllAssociative(
                'SELECT * FROM requisicion_productos WHERE requisicion_id = ?',
                [$id]
            );

            // 2) Cargar plantilla Excel
            $reader = new Xlsx();
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($this->templatePath);

            // 3) Obtener hoja correctamente (FIX del null)
            $worksheet = $spreadsheet->getSheetByName('F-SGA-SG-19');
            if (!$worksheet) {
                $worksheet = $spreadsheet->getActiveSheet();
                $this->logger->warning("La hoja 'F-SGA-SG-19' no existe. Se usa la hoja activa.");
            }

            // 4) Llenar datos
            $this->fillHeader($worksheet, $requisicion);
            $this->fillProducts($worksheet, $productos, $requisicion);
            $this->fillApprovers($worksheet, $requisicion, $productos);

            // 5) Guardar XLSX temporal
            $tempExcel = tempnam(sys_get_temp_dir(), 'req_') . '.xlsx';
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempExcel);

            $this->logger->info("Excel generado: $tempExcel");

            // 6) ConvertAPI – método oficial correcto
            ConvertApi::setApiCredentials($_ENV['CONVERT_API_SECRET'] ?? '');

            $result = ConvertApi::convert('pdf', [
                'File' => $tempExcel,
                'PageOrientation' => 'landscape',
                'AutoFit' => 'true'
            ], 'xlsx');

            // 7) Guardar PDF final
            $tempPdf = tempnam(sys_get_temp_dir(), 'req_pdf_') . '.pdf';
            $result->getFile()->save($tempPdf);

            $this->logger->info("PDF generado: $tempPdf");

            return $tempPdf;

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

            $worksheet->getCell("B$row")->setValueExplicit($idx + 1, DataType::TYPE_NUMERIC);
            $worksheet->getCell("C$row")->setValue($item['nombre'] ?? 'N/A');
            $worksheet->getCell("F$row")->setValueExplicit((int)($item['cantidad']), DataType::TYPE_NUMERIC);
            $worksheet->getCell("G$row")->setValue($item['centro_costo'] ?? 'N/A');
            $worksheet->getCell("H$row")->setValue($item['cuenta_contable'] ?? 'N/A');

            $valor = $this->parseCurrency($item['valor_estimado'] ?? 0);
            $worksheet->getCell("L$row")->setValueExplicit($valor, DataType::TYPE_NUMERIC);
            $worksheet->getStyle("L$row")->getNumberFormat()->setFormatCode('#,##0');

            $worksheet->getCell("J$row")->setValue($requisicion['presupuestada'] ? 'Sí' : 'No');
            $worksheet->getCell("M$row")->setValue($item['descripcion'] ?? 'N/A');
            $worksheet->getCell("N$row")->setValue(!empty($item['compra_tecnologica']) ? 'Sí Aplica' : 'No Aplica');
            $worksheet->getCell("R$row")->setValue(!empty($item['ergonomico']) ? 'Sí Aplica' : 'No Aplica');
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

    /**
     * Envia el XLSX a un servicio externo de conversión (CONVERT_API_URL) y guarda el PDF resultante.
     * Espera que la API responda con application/pdf en el body.
     */
    private function convertExcelToPdfUsingConvertAPI(string $excelPath, string $pdfPath): void
    {
        $convertUrl = getenv('CONVERT_API_URL') ?: ($_SERVER['CONVERT_API_URL'] ?? null);
        if (!$convertUrl) {
            throw new \RuntimeException('CONVERT_API_URL no configurado en el entorno');
        }

        if (!file_exists($excelPath)) {
            throw new \RuntimeException('Excel file not found: ' . $excelPath);
        }

        $this->logger->info("Enviando {$excelPath} a Convert API: {$convertUrl}");

        $ch = curl_init();
        $cfile = curl_file_create($excelPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', basename($excelPath));
        $post = [
            'file' => $cfile,
            // puedes añadir parámetros adicionales que la API requiera, p.e. formato=pdf
            'format' => 'pdf'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $convertUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Error calling Convert API: ' . $curlErr);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Convert API returned HTTP {$httpCode}. Body: " . substr($response, 0, 1000));
        }

        // Aceptar application/pdf o octet-stream
        if (strpos(strtolower($contentType ?? ''), 'pdf') === false && strpos(strtolower($contentType ?? ''), 'application/octet-stream') === false) {
            // si la API devolviera JSON con error, intentar parsearlo para mensaje útil
            $maybeJson = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($maybeJson['error'])) {
                throw new \RuntimeException('Convert API error: ' . $maybeJson['error']);
            }
            // si no es pdf, fallamos
            throw new \RuntimeException('Convert API did not return a PDF. Content-Type: ' . ($contentType ?? 'unknown'));
        }

        // Guardar response raw en archivo pdf
        $written = file_put_contents($pdfPath, $response);
        if ($written === false) {
            throw new \RuntimeException('No se pudo escribir el PDF en: ' . $pdfPath);
        }

        $this->logger->info("PDF guardado desde Convert API en: {$pdfPath}");
    }

    private function fixPrintSettings($worksheet): void
    {
        // orientación horizontal
        $worksheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        // ajustar a 1 página de ancho
        $worksheet->getPageSetup()->setFitToWidth(1);
        $worksheet->getPageSetup()->setFitToHeight(0);

        // área real
        $worksheet->getPageSetup()->setPrintArea('A1:W56');

        // eliminar basura fuera del rango
        $worksheet->removeColumn('X', 50);
        $worksheet->removeRow(57, 500);

        // limpiar estilos fantasma
        $worksheet->garbageCollect();
    }
}

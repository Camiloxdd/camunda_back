import express from "express";
import dotenv from "dotenv";
import { ZBClient } from "zeebe-node";

dotenv.config();

// Validar variables de entorno
const requiredVars = [
  "ZEEBE_ADDRESS",
  "ZEEBE_CLIENT_ID",
  "ZEEBE_CLIENT_SECRET",
  "ZEEBE_AUTHORIZATION_SERVER_URL",
  "BPMN_PROCESS_ID",
];

for (const v of requiredVars) {
  if (!process.env[v]) {
    console.error(`❌ Falta la variable de entorno: ${v}`);
    process.exit(1);
  }
}

// Crear cliente Zeebe
const zbc = new ZBClient({
  gatewayAddress: process.env.ZEEBE_ADDRESS,
  oAuth: {
    url: process.env.ZEEBE_AUTHORIZATION_SERVER_URL,
    audience: "zeebe.camunda.io", // 🔑 Fijo para Camunda Cloud
    clientId: process.env.ZEEBE_CLIENT_ID,
    clientSecret: process.env.ZEEBE_CLIENT_SECRET,
  },
});

console.log("✅ ENV LOADED:", {
  ADDRESS: process.env.ZEEBE_ADDRESS,
  CLIENT_ID: process.env.ZEEBE_CLIENT_ID,
  CLIENT_SECRET: process.env.ZEEBE_CLIENT_SECRET ? "****" : "MISSING",
  AUTH_URL: process.env.ZEEBE_AUTHORIZATION_SERVER_URL,
  PROCESS_ID: process.env.BPMN_PROCESS_ID,
});

const app = express();
app.use(express.json());

// Endpoint para disparar un proceso
app.post("/start", async (req, res) => {
  try {
    const result = await zbc.createProcessInstance(process.env.BPMN_PROCESS_ID, req.body || {});
    console.log("🚀 Proceso iniciado:", result.processInstanceKey);
    res.json(result);
  } catch (error) {
    console.error("❌ Error al iniciar proceso:", error);
    res.status(500).json({ error: error.message });
  }
});

// Worker de prueba
zbc.createWorker({
  taskType: "task-a",
  taskHandler: async (job) => {
    console.log("⚙️ Ejecutando task-a con variables:", job.variables);

    // Retornar el resultado directamente
    return {
      done: true,
      received: job.variables,
    };
  },
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Server corriendo en http://localhost:${PORT}`);
});

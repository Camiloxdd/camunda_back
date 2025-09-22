// worker.js
import dotenv from "dotenv";
dotenv.config();

import { ZBClient } from "zeebe-node";

const zbc = new ZBClient({
  gatewayAddress: process.env.ZEEBE_ADDRESS,
  oAuth: {
    url: process.env.ZEEBE_AUTHORIZATION_SERVER_URL,
    audience: process.env.ZEEBE_ADDRESS,
    clientId: process.env.ZEEBE_CLIENT_ID,
    clientSecret: process.env.ZEEBE_CLIENT_SECRET,
  },
});

(async () => {
  try {
    const topo = await zbc.topology();
    console.log("🔌 Conectado a Camunda Cloud, topology:", topo);
  } catch (err) {
    console.error("❌ No se pudo conectar a Camunda Cloud:", err.message || err);
    // no exit — el client reintentará según su configuración
  }
})();

console.log("⚙️ Levantando workers...");

// Worker Step 1
zbc.createWorker({
  taskType: "step-one",
  taskHandler: async (job, complete) => {
    try {
      console.log("➡️ [worker step-one] job recibido. variables:", job.variables);
      // Aquí pon tu lógica real (envío email, DB, etc.)
      // Simulación breve de trabajo:
      // await doSomething(job.variables);

      // devolver variables de salida si quieres
      await complete.success({ ...job.variables, stepOneDone: true });
      console.log("✅ [worker step-one] completado jobKey:", job.key);
    } catch (err) {
      console.error("[worker step-one] error:", err);
      await complete.failure(String(err), 0);
    }
  },
  timeout: 30000,
});

// Worker Step 2
zbc.createWorker({
  taskType: "step-two",
  taskHandler: async (job, complete) => {
    try {
      console.log("➡️ [worker step-two] job recibido. variables:", job.variables);
      await complete.success({ ...job.variables, stepTwoDone: true });
      console.log("✅ [worker step-two] completado jobKey:", job.key);
    } catch (err) {
      console.error("[worker step-two] error:", err);
      await complete.failure(String(err), 0);
    }
  },
  timeout: 30000,
});

// Worker Step 3
zbc.createWorker({
  taskType: "step-three",
  taskHandler: async (job, complete) => {
    try {
      console.log("➡️ [worker step-three] job recibido. variables:", job.variables);
      await complete.success({ ...job.variables, stepThreeDone: true });
      console.log("✅ [worker step-three] completado jobKey:", job.key);
    } catch (err) {
      console.error("[worker step-three] error:", err);
      await complete.failure(String(err), 0);
    }
  },
  timeout: 30000,
});

console.log("Workers corriendo: step-one, step-two, step-three");

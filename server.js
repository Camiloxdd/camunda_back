require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cors = require('cors');

const app = express();
const port = process.env.PORT || 4000;

// ✅ Configuración de CORS para permitir peticiones desde tu frontend
app.use(cors({
  origin: "http://localhost:3000", // tu frontend
  methods: ["GET", "POST", "PUT", "DELETE", "PATCH"],
  allowedHeaders: ["Content-Type", "Authorization"]
}));

app.use(express.json());

// Configuración (puedes usar variables de entorno)
const ZEEBE_AUTHORIZATION_SERVER_URL = 'https://login.cloud.camunda.io/oauth/token';
const ZEEBE_CLIENT_ID = 'ALq97eIT.3F9f.YFVb_XqofT-E1dEaBl';
const ZEEBE_CLIENT_SECRET = 'uUEFhht9bM_z3VsCrnCOgECUUfU6VxcDghT1FvekWuOcWxbeBUCES0jDxcMRVefb';
const CAMUNDA_TASKLIST_BASE_URL = 'https://lhr-1.tasklist.camunda.io/79fc9e4f-5c5f-40bb-9934-39516a17b786';
const AUDIENCE = 'tasklist.camunda.io';
const CAMUNDA_ZEEBE_URL = 'https://lhr-1.zeebe.camunda.io/79fc9e4f-5c5f-40bb-9934-39516a17b786'

// Función para obtener el token
async function getAccessToken() {
  const params = new URLSearchParams();
  params.append('grant_type', 'client_credentials');
  params.append('audience', AUDIENCE);
  params.append('client_id', ZEEBE_CLIENT_ID);
  params.append('client_secret', ZEEBE_CLIENT_SECRET);

  const response = await axios.post(ZEEBE_AUTHORIZATION_SERVER_URL, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  });
  return response.data.access_token;
}

// Endpoint para exponer las tareas en tu backend
app.get('/api/tasks', async (req, res) => {
  try {
    const token = await getAccessToken();
    const response = await axios.post(
      `${CAMUNDA_TASKLIST_BASE_URL}/v1/tasks/search`,
      {}, // Puedes agregar filtros aquí si lo deseas
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );
    res.json(response.data);
  } catch (err) {
    console.error('Error al consultar tareas:', err.response ? err.response.data : err.message);
    res.status(500).json({ error: 'Error al consultar tareas' });
  }
});

// Completar tarea
app.patch("/api/tasks/:id/complete", async (req, res) => {
  const { id } = req.params;
  // Espera un array de variables en el body
  const variables = req.body.variables || [];

  try {
    const token = await getAccessToken();

    const response = await axios.patch(
      `${CAMUNDA_TASKLIST_BASE_URL}/v1/tasks/${id}/complete`,
      { variables }, // variables debe ser un array de objetos {name, value}
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (err) {
    console.error("❌ Error completando tarea:", err.response?.data || err.message);
    res.status(500).json({ error: err.response?.data || err.message });
  }
});

// 🚀 Iniciar proceso
app.post("/api/process/start", async (req, res) => {
  try {
    const token = await getAccessToken();
    const { variables } = req.body;

    const response = await axios.post(
      `${CAMUNDA_ZEEBE_URL}/v2/process-instances`,
      {
        processDefinitionId: "Process_Approval", // Aquí va el ID del proceso BPMN, por ejemplo "Process_Approval"
        version: -1,   // Opcional: -1 para la última versión
        variables: variables || {},
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (error) {
    console.error(
      "❌ Error al iniciar proceso:",
      error.response?.data || error.message
    );
    res.status(500).json({ error: "Error al iniciar proceso" });
  }
});




app.patch('/api/tasks/:id/assign', async (req, res) => {
  const { id } = req.params;
  const { assignee } = req.body;

  try {
    const token = await getAccessToken();
    const response = await axios.patch(
      `${CAMUNDA_TASKLIST_BASE_URL}/v1/tasks/${id}/assign`,
      { assignee },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    res.json(response.data);
  } catch (err) {
    console.error("❌ Error al asignar tarea:", err.response?.data || err.message);
    res.status(500).json({ error: "Error al asignar tarea" });
  }
});

app.listen(port, () => {
  console.log(`Servidor escuchando en http://localhost:${port}`);
});
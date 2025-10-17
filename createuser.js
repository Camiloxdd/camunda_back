require('dotenv').config();
const mysql = require('mysql2/promise');
const bcrypt = require('bcrypt');

async function run() {
    const pool = mysql.createPool({
        host: "127.0.0.1",
        user: "root",
        password: "",
        database: "requisiciones",
        port: 3306
    });

    const nombre = 'Edison Kenneth Campos Avila'
    const correo = 'k.campos@coopidrogas.com.co';
    const contraseña = 'password123';
    const cargo = 'CoordiDevWeb';
    const telefono = '3224399893';
    const area = 'Tecnologia y Proyectos';
    const sede = 'Principal'

    const hash = await bcrypt.hash(contraseña, 10);

    const [result] = await pool.execute('INSERT INTO user (nombre, correo, contraseña, cargo, telefono, area, sede) VALUES (?, ?, ?, ?, ?, ?, ?)', [nombre, correo, hash, cargo, telefono, area, sede]);
    console.log('Inserted user id', result.insertId);
    process.exit(0);
}

run().catch(e => { console.error(e); process.exit(1); });

const fs = require('fs');
const path = require('path');

const logFilePath = path.join(__dirname, '../logs/system.log');

// Ensure log directory exists
if (!fs.existsSync(path.join(__dirname, '../logs'))) {
    fs.mkdirSync(path.join(__dirname, '../logs'));
}

const log = (message, level = 'INFO') => {
    const timestamp = new Date().toISOString();
    const logEntry = `[${timestamp}] [${level}] ${message}\n`;

    console.log(logEntry.trim()); // Print to console

    // Append to file
    fs.appendFile(logFilePath, logEntry, (err) => {
        if (err) console.error('Failed to write to log file:', err);
    });
};

module.exports = {
    info: (msg) => log(msg, 'INFO'),
    error: (msg) => log(msg, 'ERROR'),
    warn: (msg) => log(msg, 'WARN')
};
// Optional: server/initDB.js
const User = require('./models/User');
const Inventory = require('./models/Inventory');
// ... import others

const init = async () => {
    await User.createTable();
    await Inventory.createTable();
    console.log("Tables initialized");
    process.exit();
};
init();
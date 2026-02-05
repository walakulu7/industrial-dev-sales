const db = require('../config/db.config');

exports.addAsset = async (req, res) => {
    const { name, purchase_date, value, location_id, depreciation_rate } = req.body;
    
    try {
        await db.execute(
            `INSERT INTO fixed_assets 
            (name, purchase_date, original_value, current_value, location_id, depreciation_rate) 
            VALUES (?, ?, ?, ?, ?, ?)`,
            [name, purchase_date, value, value, location_id, depreciation_rate]
        );
        res.status(201).json({ message: "Asset registered successfully" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

exports.getAssets = async (req, res) => {
    try {
        const [assets] = await db.execute('SELECT * FROM fixed_assets');
        res.json(assets);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

exports.calculateDepreciation = async (req, res) => {
    // This is a simplified calculation triggering an update
    // In a real app, this might run as a Cron Job annually
    try {
        const [assets] = await db.execute('SELECT * FROM fixed_assets');
        
        for (const asset of assets) {
            // Simple Straight Line: Value reduces by Rate % every year
            const depreciationAmount = asset.original_value * (asset.depreciation_rate / 100);
            const newValue = asset.current_value - depreciationAmount;

            if (newValue > 0) {
                await db.execute(
                    'UPDATE fixed_assets SET current_value = ? WHERE id = ?',
                    [newValue, asset.id]
                );
            }
        }
        res.json({ message: "Depreciation calculated and values updated." });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};
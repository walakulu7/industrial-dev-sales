const express = require("express");
const cors = require("cors");
const dotenv = require("dotenv");
const logger = require("./utils/logger");
const db = require("./config/db.config");
const financeRoutes = require("./routes/financeRoutes");
const reportRoutes = require("./routes/reportRoutes");
const branchRoutes = require("./routes/branchRoutes");
const assetRoutes = require("./routes/assetRoutes");

// 1. Configure Environment
dotenv.config();

// 2. Import Routes
const authRoutes = require("./routes/authRoutes");
const inventoryRoutes = require("./routes/inventoryRoutes");
const adminRoutes = require("./routes/adminRoutes");
const customerRoutes = require("./routes/customerRoutes");
const salesRoutes = require("./routes/salesRoutes");
const productionRoutes = require("./routes/productionRoutes"); // <--- Imported here

// 3. Initialize App (MUST BE HERE)
const app = express();

// 4. Middleware
app.use(cors({ origin: "*" }));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// 5. Request Logging
app.use((req, res, next) => {
  logger.info(`[${req.method}] ${req.originalUrl}`);
  next();
});

// 6. Test Database Connection
db.execute("SELECT 1")
  .then(() => console.log("✅ Database connected successfully"))
  .catch((err) => console.error("❌ Database connection failed:", err.message));

// 7. Define Routes (MUST BE AFTER 'app' IS CREATED)
app.get("/", (req, res) => res.send("Textile ERP API (v2.0) is running..."));

app.use("/api/auth", authRoutes);
app.use("/api/inventory", inventoryRoutes);
app.use("/api/admin", adminRoutes);
app.use("/api/customers", customerRoutes);
app.use("/api/sales", salesRoutes);
app.use("/api/production", productionRoutes); // <--- Added correctly here
app.use("/api/finance", financeRoutes);
app.use("/api/reports", reportRoutes);
app.use("/api/branches", branchRoutes);
app.use("/api/assets", assetRoutes);

// 8. Global Error Handler
app.use((err, req, res, next) => {
  logger.error(err.message);
  res.status(500).json({
    message: err.message,
    stack: process.env.NODE_ENV === "production" ? null : err.stack,
  });
});

// 9. Start Server
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
  console.log(`\n🚀 Server running on port ${PORT}`);
  console.log(`   http://localhost:${PORT}`);
});

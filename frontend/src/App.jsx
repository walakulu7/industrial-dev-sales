import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, AuthContext } from "./context/AuthContext";
import { useContext } from "react";

// Layouts
import MainLayout from "./components/layout/MainLayout";

// Auth Pages
import Login from "./pages/Auth/Login";
import Profile from "./pages/Auth/Profile";

// Dashboard
import Dashboard from "./pages/Dashboard/AdminDashboard";

// Inventory Pages
import InventoryManager from "./pages/Inventory/YarnStock"; // Main Inventory (Stock/Transfers)
import ProductMaster from "./pages/Inventory/ProductMaster"; // Item Registry
import FinishedGoods from "./pages/Inventory/FinishedGoods"; // Finished Goods View

// Sales Pages
import NewInvoice from "./pages/Sales/NewInvoice";
import SalesHistory from "./pages/Sales/SalesHistory";
import InvoiceView from "./pages/Sales/InvoiceView";
import CreditSales from "./pages/Sales/CreditSales";

// CRM
import Customers from "./pages/CRM/Customers";

// Production
import ManageProduction from "./pages/Production/ManageProduction";
import ProductionHistory from "./pages/Production/ProductionHistory";
import ProductionOrders from "./pages/Production/ProductionOrders";

// Accounting & Admin
import AccountingDashboard from "./pages/Accounting/ProfitLossReport";
import ExpenseEntry from "./pages/Accounting/ExpenseEntry";
import ReportsHub from "./pages/Accounting/ReportsHub";
import FixedAssetRegister from "./pages/Assets/FixedAssetRegister";
import UserManagement from "./pages/Admin/UserManagement";
import Branches from "./pages/Admin/Branches";

// Protected Route Wrapper
const ProtectedRoute = ({ children }) => {
    const { user, loading } = useContext(AuthContext);
    if (loading) return <div className="p-10 text-center">Loading...</div>;
    return user ? children : <Navigate to="/login" />;
};

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    {/* Public Routes */}
                    <Route path="/login" element={<Login />} />

                    {/* Protected Routes (Wrapped in MainLayout) */}
                    <Route path="/" element={
                        <ProtectedRoute>
                            <MainLayout />
                        </ProtectedRoute>
                    }>
                        {/* Dashboard */}
                        <Route index element={<Dashboard />} />
                        <Route path="profile" element={<Profile />} />

                        {/* Inventory Module */}
                        <Route path="inventory" element={<InventoryManager />} />
                        <Route path="inventory/items" element={<ProductMaster />} />   {/* <--- FIXED THIS ROUTE */}
                        <Route path="inventory/products" element={<FinishedGoods />} />

                        {/* Sales Module */}
                        <Route path="sales" element={<SalesHistory />} />
                        <Route path="sales/new" element={<NewInvoice />} />
                        <Route path="sales/invoice/:id" element={<InvoiceView />} />
                        <Route path="sales/credit" element={<CreditSales />} />

                        {/* CRM Module */}
                        <Route path="customers" element={<Customers />} />

                        {/* Production Module */}
                        <Route path="production" element={<ManageProduction />} />
                        <Route path="production/history" element={<ProductionHistory />} />
                        <Route path="production/orders" element={<ProductionOrders />} />

                        {/* Finance & Assets */}
                        <Route path="finance" element={<AccountingDashboard />} />
                        <Route path="finance/transaction" element={<ExpenseEntry />} />
                        <Route path="assets" element={<FixedAssetRegister />} />
                        
                        {/* Administration */}
                        <Route path="reports" element={<ReportsHub />} />
                        <Route path="admin/users" element={<UserManagement />} />
                        <Route path="branches" element={<Branches />} />

                    </Route>
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

export default App;

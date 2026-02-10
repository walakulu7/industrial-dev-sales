import { BrowserRouter, Routes, Route, Navigate, Outlet } from "react-router-dom";
import { AuthProvider, AuthContext } from "./context/AuthContext";
import { useContext } from "react";
import { ROLES } from "./utils/roleConstants";

// Layouts
import MainLayout from "./components/layout/MainLayout";

// Auth Pages
import Login from "./pages/Auth/Login";
import Profile from "./pages/Auth/Profile";

// Dashboard
import Dashboard from "./pages/Dashboard/AdminDashboard";

// Inventory Pages
import InventoryManager from "./pages/Inventory/YarnStock"; // File might be named YarnStock.jsx
import ProductMaster from "./pages/Inventory/ProductMaster";
import FinishedGoods from "./pages/Inventory/FinishedGoods";

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
import AccountingDashboard from "./pages/Accounting/ProfitLossReport"; // File might be named ProfitLossReport.jsx
import ExpenseEntry from "./pages/Accounting/ExpenseEntry";
import ReportsHub from "./pages/Accounting/ReportsHub";
import FixedAssetRegister from "./pages/Assets/FixedAssetRegister";
import UserManagement from "./pages/Admin/UserManagement";
import Branches from "./pages/Admin/Branches";

// 1. Basic Protected Route (Checks if logged in)
const ProtectedRoute = ({ children }) => {
    const { user, loading } = useContext(AuthContext);
    if (loading) return <div className="p-10 text-center">Loading...</div>;
    return user ? children : <Navigate to="/login" />;
};

// 2. Role Based Route (FIXED: Uses Outlet)
const RoleRoute = ({ allowedRoles }) => {
    const { user, loading } = useContext(AuthContext);

    if (loading) return <div>Loading...</div>;

    // If not logged in or wrong role, send to Dashboard
    if (!user || !allowedRoles.includes(user.user.role)) {
        return <Navigate to="/" replace />;
    }

    // This <Outlet /> is crucial! It renders the child route (e.g. Inventory)
    return <Outlet />;
};

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<Login />} />

                    {/* Main Layout Wrapper */}
                    <Route path="/" element={
                        <ProtectedRoute>
                            <MainLayout />
                        </ProtectedRoute>
                    }>
                        {/* --- DASHBOARD (Everyone) --- */}
                        <Route index element={<Dashboard />} />
                        <Route path="profile" element={<Profile />} />

                        {/* --- OPERATIONS (Admin, Director, Officer) --- */}
                        <Route element={<RoleRoute allowedRoles={[ROLES.ADMIN, ROLES.DIRECTOR, ROLES.OFFICER]} />}>
                            <Route path="inventory" element={<InventoryManager />} />
                            <Route path="production" element={<ManageProduction />} />
                            <Route path="production/history" element={<ProductionHistory />} />
                            <Route path="production/orders" element={<ProductionOrders />} />
                        </Route>

                        {/* --- SALES (Everyone) --- */}
                        <Route path="sales" element={<SalesHistory />} />
                        <Route path="sales/new" element={<NewInvoice />} />
                        <Route path="sales/invoice/:id" element={<InvoiceView />} />
                        <Route path="customers" element={<Customers />} />

                        {/* --- FINANCE (Admin, Director, Accountant) --- */}
                        <Route element={<RoleRoute allowedRoles={[ROLES.ADMIN, ROLES.DIRECTOR, ROLES.ACCOUNTANT]} />}>
                            <Route path="sales/credit" element={<CreditSales />} />
                            <Route path="finance" element={<AccountingDashboard />} />
                            <Route path="finance/transaction" element={<ExpenseEntry />} />
                            <Route path="assets" element={<FixedAssetRegister />} />
                            <Route path="reports" element={<ReportsHub />} />
                        </Route>

                        {/* --- ADMIN ONLY --- */}
                        <Route element={<RoleRoute allowedRoles={[ROLES.ADMIN]} />}>
                            <Route path="admin/users" element={<UserManagement />} />
                        </Route>

                        {/* --- MASTER DATA (Admin, Director) --- */}
                        <Route element={<RoleRoute allowedRoles={[ROLES.ADMIN, ROLES.DIRECTOR]} />}>
                            <Route path="inventory/items" element={<ProductMaster />} />
                            <Route path="inventory/products" element={<FinishedGoods />} />
                            <Route path="branches" element={<Branches />} />
                        </Route>

                    </Route>
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

export default App;

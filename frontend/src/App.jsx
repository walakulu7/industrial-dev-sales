import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, AuthContext } from "./context/AuthContext";
import { useContext } from "react";
import UserManagement from "./pages/Admin/UserManagement";
import Customers from "./pages/CRM/Customers";
import ProductMaster from "./pages/Inventory/ProductMaster";
import Branches from "./pages/Admin/Branches";
import ReportsHub from "./pages/Accounting/ReportsHub";
import Profile from "./pages/Auth/Profile";
import InvoiceView from "./pages/Sales/InvoiceView";
import SalesHistory from "./pages/Sales/SalesHistory";


// Layouts
import MainLayout from "./components/layout/MainLayout";

// Pages - Auth
import Login from "./pages/Auth/Login";
import Unauthorized from "./pages/Auth/Unauthorized";

// Pages - Dashboard
import Dashboard from "./pages/Dashboard/Dashboard";

// Pages - Inventory
import YarnStock from "./pages/Inventory/YarnStock";
import FinishedGoods from "./pages/Inventory/FinishedGoods";

// Pages - Sales
import NewInvoice from "./pages/Sales/NewInvoice";
import CreditSales from "./pages/Sales/CreditSales";

// Pages - Production
import ManageProduction from "./pages/Production/ManageProduction";
import ProductionHistory from "./pages/Production/ProductionHistory";

// Pages - Accounting
import ProfitLossReport from "./pages/Accounting/ProfitLossReport";
import ExpenseEntry from "./pages/Accounting/ExpenseEntry";

// Pages - Assets
import FixedAssetRegister from "./pages/Assets/FixedAssetRegister";

// Protected Route Component
const ProtectedRoute = ({ children }) => {
    const { user, loading } = useContext(AuthContext);
    if (loading) return <div className="p-10">Loading...</div>;
    return user ? children : <Navigate to="/login" />;
};

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<Login />} />
                    <Route path="/unauthorized" element={<Unauthorized />} />

                    {/* Main App Layout */}
                    <Route path="/" element={
                        <ProtectedRoute>
                            <MainLayout />
                        </ProtectedRoute>
                    }>
                        {/* Admin Routes */}
                        <Route path="admin/users" element={<UserManagement />} />
                        <Route path="branches" element={<Branches />} />
                        <Route path="reports" element={<ReportsHub />} />
                        {/* Dashboard */}
                        <Route index element={<Dashboard />} />

                        {/* Inventory */}
                        <Route path="inventory" element={<YarnStock />} />
                        <Route path="inventory/products" element={<FinishedGoods />} />
                        <Route path="products" element={<ProductMaster />} />

                        {/* Sales */}
                        <Route path="sales/new" element={<NewInvoice />} />
                        <Route path="sales/credit" element={<CreditSales />} />
                        <Route path="/sales" element={<SalesHistory />} />
                        <Route path="/sales/invoice/:id" element={<InvoiceView />} />

                        {/* Production */}
                        <Route path="production" element={<ManageProduction />} />
                        <Route path="production/history" element={<ProductionHistory />} />

                        {/* Finance/Accounting */}
                        <Route path="finance" element={<ProfitLossReport />} />
                        <Route path="finance/pnl" element={<ProfitLossReport />} />
                        <Route path="finance/transaction" element={<ExpenseEntry />} />

                        {/* Assets */}
                        <Route path="assets" element={<FixedAssetRegister />} />

                        {/* CRM */}
                        <Route path="customers" element={<Customers />} />

                        {/* Profile Route */}
                        <Route path="/profile" element={<Profile />} />

                        {/* Invoice View Route */}
                        <Route path="/sales/new" element={<NewInvoice />} />
                        <Route path="/sales/invoice/:id" element={<InvoiceView />} />

                    </Route>
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

export default App;
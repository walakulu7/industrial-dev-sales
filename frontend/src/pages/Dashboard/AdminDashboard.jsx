import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import {
    FaFileInvoice, FaChartLine, FaCreditCard, FaExclamationTriangle,
    FaIndustry, FaBolt, FaShoppingCart, FaWarehouse, FaCalculator, FaPlus
} from "react-icons/fa";

const AdminDashboard = () => {
    const [stats, setStats] = useState({
        today_sales: 0,
        monthly_sales: 0,
        pending_credit: 0,
        low_stock: 0,
        active_production: 0
    });

    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const res = await API.get('/reports/dashboard');
                setStats(res.data);
            } catch (err) {
                console.error("Error fetching stats:", err);
            }
        };
        fetchStats();
    }, []);

    return (
        <div className="space-y-8 animate-fade-in-up pb-10">

            {/* --- 1. Header Section --- */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800 tracking-tight">Dashboard Overview</h1>
                    <p className="text-gray-500 mt-1 font-medium">{today}</p>
                </div>
                {/* Quick Action Button (Visible on all screens) */}
                <Link to="/sales/new" className="bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg hover:bg-indigo-700 hover:shadow-indigo-200 transition flex items-center font-bold">
                    <FaPlus className="mr-2" /> New Invoice
                </Link>
            </div>

            {/* --- 2. Key Metrics Grid --- */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <StatCard
                    title="Today's Sales"
                    value={formatCurrency(stats.today_sales)}
                    icon={<FaFileInvoice />}
                    color="text-blue-600"
                    bg="bg-blue-50"
                    trend="+ Daily"
                />

                <StatCard
                    title="Monthly Revenue"
                    value={formatCurrency(stats.monthly_sales)}
                    icon={<FaChartLine />}
                    color="text-emerald-600"
                    bg="bg-emerald-50"
                    trend="Current Month"
                />

                <StatCard
                    title="Pending Credit"
                    value={formatCurrency(stats.pending_credit)}
                    icon={<FaCreditCard />}
                    color="text-amber-500"
                    bg="bg-amber-50"
                    trend="To Collect"
                />

                <StatCard
                    title="Production Lines"
                    value={stats.active_production}
                    icon={<FaIndustry />}
                    color="text-indigo-600"
                    bg="bg-indigo-50"
                    trend="Active Orders"
                />
            </div>

            {/* --- 3. Alert Section (Only shows if necessary) --- */}
            {stats.low_stock > 0 && (
                <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg flex items-center justify-between shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className="bg-white p-2 rounded-full text-red-500 shadow-sm">
                            <FaExclamationTriangle />
                        </div>
                        <div>
                            <h3 className="font-bold text-red-700">Low Stock Alert</h3>
                            <p className="text-sm text-red-600">{stats.low_stock} items are below reorder level.</p>
                        </div>
                    </div>
                    <Link to="/inventory" className="text-sm font-bold text-red-700 underline hover:text-red-800">Check Inventory &rarr;</Link>
                </div>
            )}

            {/* --- 4. Operations Modules --- */}
            <h2 className="text-xl font-bold text-slate-700 pt-4">Operational Modules</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                <ModuleCard title="Sales & Billing" icon={<FaShoppingCart />} color="blue">
                    <ModuleLink to="/sales/new" label="Create Invoice" />
                    <ModuleLink to="/sales" label="Sales History" />
                    <ModuleLink to="/customers" label="Customer Database" />
                </ModuleCard>

                <ModuleCard title="Inventory Control" icon={<FaWarehouse />} color="emerald">
                    <ModuleLink to="/inventory" label="Current Stock" />
                    <ModuleLink to="/inventory/products" label="Finished Goods" />
                    <ModuleLink to="/inventory/items" label="Product Master" />
                </ModuleCard>

                <ModuleCard title="Manufacturing" icon={<FaIndustry />} color="indigo">
                    <ModuleLink to="/production" label="Record Output" />
                    <ModuleLink to="/production/orders" label="Production Orders" />
                    <ModuleLink to="/production/history" label="Logs & History" />
                </ModuleCard>

                <ModuleCard title="Finance & Assets" icon={<FaCalculator />} color="amber">
                    <ModuleLink to="/finance" label="Profit & Loss" />
                    <ModuleLink to="/finance/transaction" label="Record Expense" />
                    <ModuleLink to="/assets" label="Fixed Assets" />
                </ModuleCard>

            </div>

            {/* Footer */}
            <div className="text-center pt-8 border-t border-gray-200">
                <p className="text-sm text-gray-400">&copy; 2026 Textile Management System. All rights reserved.</p>
            </div>
        </div>
    );
};

// --- Modern Components ---

const StatCard = ({ title, value, icon, color, bg, trend }) => (
    <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300">
        <div className="flex justify-between items-start">
            <div>
                <p className="text-sm font-semibold text-gray-500 uppercase tracking-wide">{title}</p>
                <h3 className="text-2xl font-bold text-slate-800 mt-1">{value}</h3>
            </div>
            <div className={`p-3 rounded-lg ${bg} ${color} text-xl`}>
                {icon}
            </div>
        </div>
        <div className="mt-4 flex items-center text-xs font-medium text-gray-400">
            <span className="bg-gray-100 px-2 py-1 rounded text-gray-600 mr-2">{trend}</span>
            <span>Auto-updated</span>
        </div>
    </div>
);

const ModuleCard = ({ title, icon, color, children }) => {
    // Tailwind dynamic color classes
    const colors = {
        blue: "text-blue-600 bg-blue-50 border-t-blue-500",
        emerald: "text-emerald-600 bg-emerald-50 border-t-emerald-500",
        indigo: "text-indigo-600 bg-indigo-50 border-t-indigo-500",
        amber: "text-amber-600 bg-amber-50 border-t-amber-500"
    };

    return (
        <div className={`bg-white rounded-xl shadow-sm border border-gray-100 border-t-4 ${colors[color].split(' ')[2]} flex flex-col h-full hover:shadow-lg transition-all duration-300`}>
            <div className={`p-4 flex items-center gap-3 border-b border-gray-100 ${colors[color].split(' ')[1]}`}>
                <span className={`text-xl ${colors[color].split(' ')[0]}`}>{icon}</span>
                <h3 className="font-bold text-slate-700">{title}</h3>
            </div>
            <div className="p-4 flex flex-col gap-2 flex-1">
                {children}
            </div>
        </div>
    );
};

const ModuleLink = ({ to, label }) => (
    <Link to={to} className="group flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition">
        {label}
        <span className="text-gray-300 group-hover:translate-x-1 transition-transform duration-200">&rarr;</span>
    </Link>
);

export default AdminDashboard;

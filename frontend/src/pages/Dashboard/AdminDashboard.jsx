import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { 
    FaFileInvoice, FaChartLine, FaCreditCard, FaExclamationTriangle, 
    FaIndustry, FaBolt, FaShoppingCart, FaWarehouse, FaCalculator 
} from "react-icons/fa";

const AdminDashboard = () => {
    const [stats, setStats] = useState({ 
        sales_today: 0, 
        outstanding_credit: 0, 
        low_stock_items: [],
        active_orders: 3 // Placeholder until backend is updated
    });
    
    // Date for Header
    const today = new Date().toLocaleDateString('en-US', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });

    useEffect(() => {
        API.get('/reports/dashboard')
           .then(res => setStats(res.data))
           .catch(err => console.error(err));
    }, []);

    return (
        <div className="space-y-6 animate-fade-in-up">
            {/* Header Section */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaBolt className="text-yellow-500" /> Dashboard
                    </h1>
                    <p className="text-gray-500 text-sm">Viskampiyasa Main Office</p>
                </div>
                <div className="text-right mt-2 md:mt-0">
                    <p className="text-slate-600 font-medium">{today}</p>
                    <p className="text-xs text-blue-600 font-semibold">System Administrator</p>
                </div>
            </div>

            {/* Stat Cards Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <StatCard 
                    title="Today's Sales" 
                    value={formatCurrency(stats.sales_today)} 
                    subtitle="Includes cash & credit" 
                    icon={<FaFileInvoice />} 
                    color="bg-blue-500" 
                />
                <StatCard 
                    title="Monthly Sales" 
                    value={formatCurrency(stats.sales_today * 25)} // Placeholder logic
                    subtitle="This month" 
                    icon={<FaChartLine />} 
                    color="bg-emerald-500" 
                />
                <StatCard 
                    title="Pending Credit" 
                    value={formatCurrency(stats.outstanding_credit)} 
                    subtitle="Total Outstanding" 
                    icon={<FaCreditCard />} 
                    color="bg-amber-500" 
                />
                <StatCard 
                    title="Low Stock" 
                    value={stats.low_stock_items?.length || 0} 
                    subtitle="Items below reorder" 
                    icon={<FaExclamationTriangle />} 
                    color="bg-rose-500" 
                />
                <StatCard 
                    title="Production" 
                    value={stats.active_orders} 
                    subtitle="Active Lines" 
                    icon={<FaIndustry />} 
                    color="bg-cyan-500" 
                />
                {/* Quick Action Card */}
                <div className="bg-slate-700 text-white p-4 rounded-lg shadow flex flex-col justify-center items-center hover:bg-slate-800 transition cursor-pointer">
                    <h3 className="font-bold mb-2">Quick Action</h3>
                    <Link to="/sales/new" className="bg-white text-slate-800 px-4 py-1 rounded-full text-sm font-bold hover:bg-gray-100">
                        + New Invoice
                    </Link>
                </div>
            </div>

            {/* Action Panels Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <ActionPanel title="Sales" icon={<FaShoppingCart />} color="border-blue-500" headerColor="bg-blue-500">
                    <ActionButton to="/sales/new" label="+ New Invoice" />
                    <ActionButton to="/sales/credit" label="View Credit Sales" />
                    <ActionButton to="/customers" label="Manage Customers" />
                </ActionPanel>

                <ActionPanel title="Inventory" icon={<FaWarehouse />} color="border-emerald-500" headerColor="bg-emerald-500">
                    <ActionButton to="/inventory" label="Check Stock Levels" />
                    <ActionButton to="/inventory/products" label="Finished Goods" />
                    <ActionButton to="/products" label="Item Master" />
                </ActionPanel>

                <ActionPanel title="Production" icon={<FaIndustry />} color="border-cyan-500" headerColor="bg-cyan-500">
                    <ActionButton to="/production" label="Record Output" />
                    <ActionButton to="/production/history" label="Production History" />
                    <ActionButton to="#" label="New Order (Coming Soon)" />
                </ActionPanel>

                <ActionPanel title="Accounting" icon={<FaCalculator />} color="border-amber-500" headerColor="bg-amber-500">
                    <ActionButton to="/finance/pnl" label="P&L Report" />
                    <ActionButton to="/finance/transaction" label="Record Expense" />
                    <ActionButton to="/assets" label="Fixed Assets" />
                </ActionPanel>
            </div>
            
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

// Sub-components for cleaner code
const StatCard = ({ title, value, subtitle, icon, color }) => (
    <div className={`${color} text-white p-4 rounded-lg shadow-lg flex flex-col justify-between h-32 relative overflow-hidden`}>
        <div>
            <h3 className="text-sm font-medium opacity-90">{title}</h3>
            <p className="text-2xl font-bold mt-1">{value}</p>
            <p className="text-xs opacity-75 mt-1">{subtitle}</p>
        </div>
        <div className="absolute right-3 bottom-3 text-4xl opacity-20">{icon}</div>
    </div>
);

const ActionPanel = ({ title, icon, color, headerColor, children }) => (
    <div className={`bg-white rounded-lg shadow border-t-4 ${color} flex flex-col h-full`}>
        <div className={`${headerColor} text-white p-3 font-bold flex items-center gap-2`}>
            {icon} {title}
        </div>
        <div className="p-4 flex flex-col gap-2 flex-1">
            {children}
        </div>
    </div>
);

const ActionButton = ({ to, label }) => (
    <Link to={to} className="block w-full text-center py-2 border border-gray-200 rounded text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-sm font-medium">
        {label}
    </Link>
);

export default AdminDashboard;
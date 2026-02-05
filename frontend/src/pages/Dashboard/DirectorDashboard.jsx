import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaChartLine, FaIndustry, FaExclamationTriangle } from "react-icons/fa";

const DirectorDashboard = () => {
    const [stats, setStats] = useState({ sales_today: 0, outstanding_credit: 0, low_stock_items: [] });

    useEffect(() => {
        // Fetch dashboard stats (ensure backend route exists as per previous steps)
        API.get('/reports/dashboard').then(res => setStats(res.data)).catch(err => console.error(err));
    }, []);

    return (
        <div className="space-y-6">
            <h1 className="text-3xl font-bold text-slate-800">Director Overview</h1>
            
            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-600 flex items-center">
                    <div className="p-4 bg-blue-100 rounded-full mr-4"><FaChartLine className="text-blue-600 text-xl"/></div>
                    <div>
                        <p className="text-gray-500">Sales Today</p>
                        <h2 className="text-2xl font-bold">{formatCurrency(stats.sales_today)}</h2>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-orange-500 flex items-center">
                    <div className="p-4 bg-orange-100 rounded-full mr-4"><FaIndustry className="text-orange-600 text-xl"/></div>
                    <div>
                        <p className="text-gray-500">Outstanding Credit</p>
                        <h2 className="text-2xl font-bold">{formatCurrency(stats.outstanding_credit)}</h2>
                    </div>
                </div>
            </div>

            {/* Low Stock Alerts */}
            <div className="bg-white p-6 rounded-lg shadow">
                <h3 className="text-xl font-bold mb-4 flex items-center">
                    <FaExclamationTriangle className="text-red-500 mr-2" /> Critical Stock Alerts
                </h3>
                <table className="w-full text-left">
                    <thead className="bg-gray-100">
                        <tr><th className="p-2">Item</th><th className="p-2">Location</th><th className="p-2">Qty</th></tr>
                    </thead>
                    <tbody>
                        {stats.low_stock_items.length > 0 ? stats.low_stock_items.map((item, i) => (
                            <tr key={i} className="border-b">
                                <td className="p-2">{item.name}</td>
                                <td className="p-2">{item.location}</td>
                                <td className="p-2 text-red-600 font-bold">{item.quantity}</td>
                            </tr>
                        )) : (
                            <tr><td colSpan="3" className="p-4 text-center text-gray-500">Inventory levels are healthy.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default DirectorDashboard;
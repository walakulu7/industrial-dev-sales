import { Link } from "react-router-dom";
import { FaFileInvoiceDollar, FaCalculator, FaCoins } from "react-icons/fa";

const AccountantDashboard = () => {
    return (
        <div className="space-y-6">
            <h1 className="text-3xl font-bold text-slate-800">Financial Dashboard</h1>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Link to="/finance/pnl" className="bg-white p-8 rounded shadow hover:shadow-lg transition flex flex-col items-center justify-center text-center cursor-pointer border border-gray-200">
                    <FaCalculator className="text-5xl text-slate-700 mb-4" />
                    <h3 className="text-xl font-bold text-slate-900">Profit & Loss Report</h3>
                    <p className="text-gray-500 mt-2">Generate monthly or annual financial statements</p>
                </Link>

                <Link to="/sales/credit" className="bg-white p-8 rounded shadow hover:shadow-lg transition flex flex-col items-center justify-center text-center cursor-pointer border border-gray-200">
                    <FaFileInvoiceDollar className="text-5xl text-orange-600 mb-4" />
                    <h3 className="text-xl font-bold text-slate-900">Credit Management</h3>
                    <p className="text-gray-500 mt-2">Track outstanding customer balances</p>
                </Link>

                <Link to="/assets" className="bg-white p-8 rounded shadow hover:shadow-lg transition flex flex-col items-center justify-center text-center cursor-pointer border border-gray-200">
                    <FaCoins className="text-5xl text-green-600 mb-4" />
                    <h3 className="text-xl font-bold text-slate-900">Fixed Assets</h3>
                    <p className="text-gray-500 mt-2">Manage asset register and depreciation</p>
                </Link>
            </div>
        </div>
    );
};

export default AccountantDashboard;
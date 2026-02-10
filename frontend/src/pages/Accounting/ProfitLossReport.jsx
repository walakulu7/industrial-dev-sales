import { useEffect, useState, useContext } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaChartPie, FaPlusCircle, FaMoneyBillWave, FaArrowDown, FaArrowUp, FaArrowLeft } from "react-icons/fa";

const AccountingDashboard = () => {
    const navigate = useNavigate();
    const { user } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState("overview"); // 'overview' or 'expenses'

    // Stats State
    const [stats, setStats] = useState({ revenue: 0, expenses: 0, profit: 0 });
    const [expensesList, setExpensesList] = useState([]);
    const [loading, setLoading] = useState(false);

    // Expense Form
    const [expenseForm, setExpenseForm] = useState({
        date: new Date().toISOString().split('T')[0],
        category: "Utilities",
        amount: "",
        description: ""
    });

    const categories = ["Utilities", "Rent", "Salaries", "Maintenance", "Transport", "Office Supplies", "Raw Material Purchase"];

    // Fetch Data
    const loadData = async () => {
        setLoading(true);
        try {
            const [statsRes, expRes] = await Promise.all([
                API.get('/finance/stats'),
                API.get('/finance/expenses')
            ]);
            setStats(statsRes.data);
            setExpensesList(expRes.data);
        } catch (err) { console.error(err); }
        finally { setLoading(false); }
    };

    useEffect(() => { loadData(); }, []);

    // Handle Expense Submit
    const handleExpenseSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.post('/finance/expenses', { ...expenseForm, user_id: user.user.id });
            alert("Expense Recorded");
            setExpenseForm({ date: "", category: "Utilities", amount: "", description: "" });
            loadData();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Failed to save expense");
        }
    };

    return (
        <div className="animate-fade-in-up">
            {/* Header with Back Button */}
            <div className="mb-6 flex items-center justify-between">
                <button onClick={() => navigate(-1)} className="text-gray-500 hover:text-indigo-600 flex items-center gap-2 transition">
                    <FaArrowLeft /> Back
                </button>
            </div>
            <h1 className="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <FaChartPie className="text-indigo-600" /> Financial Accounting
            </h1>

            {/* Navigation Tabs */}
            <div className="flex border-b border-gray-200 mb-6">
                <button onClick={() => setActiveTab("overview")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'overview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'}`}>
                    Overview & P&L
                </button>
                <button onClick={() => setActiveTab("expenses")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'expenses' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'}`}>
                    Record Expenses
                </button>
            </div>

            {/* --- TAB 1: OVERVIEW --- */}
            {activeTab === "overview" && (
                <div>
                    {/* Stat Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        {/* Revenue */}
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                            <div className="flex justify-between items-center">
                                <div>
                                    <p className="text-gray-500 text-xs font-bold uppercase">Total Revenue</p>
                                    <p className="text-3xl font-bold text-green-600">{formatCurrency(stats.revenue)}</p>
                                </div>
                                <FaArrowUp className="text-3xl text-green-200" />
                            </div>
                        </div>

                        {/* Expenses */}
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
                            <div className="flex justify-between items-center">
                                <div>
                                    <p className="text-gray-500 text-xs font-bold uppercase">Total Expenses</p>
                                    <p className="text-3xl font-bold text-red-600">{formatCurrency(stats.expenses)}</p>
                                </div>
                                <FaArrowDown className="text-3xl text-red-200" />
                            </div>
                        </div>

                        {/* Net Profit */}
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                            <div className="flex justify-between items-center">
                                <div>
                                    <p className="text-gray-500 text-xs font-bold uppercase">Net Profit</p>
                                    <p className={`text-3xl font-bold ${stats.profit >= 0 ? 'text-slate-800' : 'text-red-600'}`}>
                                        {formatCurrency(stats.profit)}
                                    </p>
                                </div>
                                <FaMoneyBillWave className="text-3xl text-blue-200" />
                            </div>
                        </div>
                    </div>

                    {/* Recent Transactions Table (Simplified P&L) */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h3 className="font-bold text-lg text-gray-700 mb-4">Recent Expenses</h3>
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-100 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th className="p-3">Date</th>
                                    <th className="p-3">Category</th>
                                    <th className="p-3">Description</th>
                                    <th className="p-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {loading ? (
                                    <tr><td colSpan="4" className="p-8 text-center text-gray-500">Loading expenses...</td></tr>
                                ) : expensesList.length > 0 ? expensesList.map((exp) => (
                                    <tr key={exp.expense_id}>
                                        <td className="p-3">{new Date(exp.expense_date).toLocaleDateString()}</td>
                                        <td className="p-3"><span className="bg-gray-200 px-2 py-1 rounded text-xs">{exp.category}</span></td>
                                        <td className="p-3 text-gray-600">{exp.description}</td>
                                        <td className="p-3 text-right font-bold text-red-600">-{formatCurrency(exp.amount)}</td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="4" className="p-8 text-center text-gray-500">No expenses found.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* --- TAB 2: EXPENSE ENTRY --- */}
            {activeTab === "expenses" && (
                <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow border border-gray-200">
                    <h2 className="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <FaPlusCircle /> Record Operating Expense
                    </h2>

                    <form onSubmit={handleExpenseSubmit} className="space-y-6">
                        <div className="grid grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-bold text-gray-600 mb-2">Date</label>
                                <input type="date" required className="w-full border p-3 rounded"
                                    value={expenseForm.date} onChange={e => setExpenseForm({ ...expenseForm, date: e.target.value })} />
                            </div>
                            <div>
                                <label className="block text-sm font-bold text-gray-600 mb-2">Category</label>
                                <select className="w-full border p-3 rounded"
                                    value={expenseForm.category} onChange={e => setExpenseForm({ ...expenseForm, category: e.target.value })}>
                                    {categories.map(c => <option key={c} value={c}>{c}</option>)}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Amount (LKR)</label>
                            <input type="number" step="0.01" required className="w-full border p-3 rounded font-bold text-lg" placeholder="0.00"
                                value={expenseForm.amount} onChange={e => setExpenseForm({ ...expenseForm, amount: e.target.value })} />
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Description</label>
                            <textarea rows="3" className="w-full border p-3 rounded" placeholder="Details about the expense..."
                                value={expenseForm.description} onChange={e => setExpenseForm({ ...expenseForm, description: e.target.value })}></textarea>
                        </div>

                        <button type="submit" className="w-full bg-red-600 text-white py-3 rounded font-bold hover:bg-red-700 transition">
                            Save Expense
                        </button>
                    </form>
                </div>
            )}
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default AccountingDashboard;

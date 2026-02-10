import { useState, useContext } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { FaMoneyBillWave, FaSave, FaArrowLeft } from "react-icons/fa";

const ExpenseEntry = () => {
    const { user } = useContext(AuthContext);
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);

    const [formData, setFormData] = useState({
        type: "Expense", // Default
        category: "Utilities",
        amount: "",
        description: "",
        date: new Date().toISOString().split('T')[0]
    });

    const categories = [
        "Utilities (Electric/Water)",
        "Rent",
        "Salaries & Wages",
        "Maintenance",
        "Transport / Fuel",
        "Office Supplies",
        "Raw Material Purchase",
        "Marketing",
        "Other"
    ];

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            // We map 'type' to the backend logic. 
            // Currently, our backend 'addExpense' handles expenses.
            // If you expand to 'Manual Income', you'd need a different endpoint.

            await API.post('/finance/expenses', {
                date: formData.date,
                category: formData.category,
                amount: formData.amount,
                description: formData.description,
                user_id: user.user.id
            });

            alert("Transaction Recorded Successfully!");
            navigate('/finance'); // Redirect to Accounting Dashboard
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Failed to save transaction");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto animate-fade-in-up">

            {/* Header with Back Button */}
            <div className="mb-6 flex items-center justify-between">
                <button onClick={() => navigate(-1)} className="text-gray-500 hover:text-indigo-600 flex items-center gap-2 transition">
                    <FaArrowLeft /> Back
                </button>
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaMoneyBillWave className="text-indigo-600" /> Financial Transaction
                </h1>
            </div>

            {/* Form Card */}
            <div className="bg-white p-8 rounded-lg shadow-md border border-gray-200">
                <h2 className="text-lg font-bold text-gray-700 mb-6 border-b pb-2">Record New Transaction</h2>

                <form onSubmit={handleSubmit} className="space-y-5">

                    <div className="grid grid-cols-2 gap-6">
                        {/* Transaction Type */}
                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Transaction Type</label>
                            <select
                                className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none bg-gray-50"
                                value={formData.type}
                                onChange={e => setFormData({ ...formData, type: e.target.value })}
                            >
                                <option value="Expense">Expense (Money Out)</option>
                                {/* Future: <option value="Income">Income (Money In)</option> */}
                            </select>
                        </div>

                        {/* Category */}
                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Category</label>
                            <select
                                className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none"
                                value={formData.category}
                                onChange={e => setFormData({ ...formData, category: e.target.value })}
                            >
                                {categories.map((cat, index) => (
                                    <option key={index} value={cat}>{cat}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Amount */}
                    <div>
                        <label className="block text-sm font-bold text-gray-600 mb-2">Amount (LKR)</label>
                        <input
                            type="number" step="0.01" required
                            className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none text-lg font-semibold"
                            placeholder="0.00"
                            value={formData.amount}
                            onChange={e => setFormData({ ...formData, amount: e.target.value })}
                        />
                    </div>

                    {/* Description */}
                    <div>
                        <label className="block text-sm font-bold text-gray-600 mb-2">Description / Notes</label>
                        <textarea
                            rows="3"
                            className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none"
                            placeholder="e.g., Paid electricity bill for January"
                            value={formData.description}
                            onChange={e => setFormData({ ...formData, description: e.target.value })}
                        ></textarea>
                    </div>

                    {/* Date */}
                    <div>
                        <label className="block text-sm font-bold text-gray-600 mb-2">Date</label>
                        <input
                            type="date" required
                            className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none"
                            value={formData.date}
                            onChange={e => setFormData({ ...formData, date: e.target.value })}
                        />
                    </div>

                    {/* Submit Button */}
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-slate-900 text-white py-3 rounded-lg font-bold hover:bg-slate-800 transition flex justify-center items-center gap-2 mt-4"
                    >
                        {loading ? "Saving..." : <><FaSave /> Save Transaction</>}
                    </button>

                </form>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default ExpenseEntry;

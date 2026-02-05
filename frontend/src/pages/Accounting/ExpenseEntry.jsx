import { useState } from "react";
import API from "../../api/axiosConfig";
import { ENDPOINTS } from "../../api/endpoints";
import { useGlobal } from "../../context/GlobalState";

const ExpenseEntry = () => {
    const { showAlert, setIsLoading } = useGlobal();
    const [formData, setFormData] = useState({
        description: "",
        type: "Expense", // Default
        category: "Utility",
        amount: "",
        date: new Date().toISOString().split('T')[0]
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            await API.post(ENDPOINTS.FINANCE.ADD_TRANSACTION, formData);
            showAlert("Transaction recorded successfully", "success");
            setFormData({ ...formData, description: "", amount: "" });
        } catch (error) {
            showAlert(error.response?.data?.error || "Failed to record transaction", "error");
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto bg-white p-8 rounded shadow-md">
            <h2 className="text-2xl font-bold mb-6 text-slate-800">Record Financial Transaction</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Type</label>
                        <select 
                            className="mt-1 w-full border border-gray-300 rounded p-2"
                            value={formData.type}
                            onChange={(e) => setFormData({...formData, type: e.target.value})}
                        >
                            <option value="Expense">Expense</option>
                            <option value="Income">Manual Income</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Category</label>
                        <select 
                            className="mt-1 w-full border border-gray-300 rounded p-2"
                            value={formData.category}
                            onChange={(e) => setFormData({...formData, category: e.target.value})}
                        >
                            <option value="Utility">Utility (Electric/Water)</option>
                            <option value="Salary">Salaries</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Logistics">Logistics/Transport</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Description</label>
                    <input 
                        required
                        type="text" 
                        className="mt-1 w-full border border-gray-300 rounded p-2"
                        value={formData.description}
                        onChange={(e) => setFormData({...formData, description: e.target.value})}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Amount (LKR)</label>
                    <input 
                        required
                        type="number" 
                        className="mt-1 w-full border border-gray-300 rounded p-2"
                        value={formData.amount}
                        onChange={(e) => setFormData({...formData, amount: e.target.value})}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Date</label>
                    <input 
                        type="date" 
                        className="mt-1 w-full border border-gray-300 rounded p-2"
                        value={formData.date}
                        onChange={(e) => setFormData({...formData, date: e.target.value})}
                    />
                </div>

                <button type="submit" className="w-full bg-slate-900 text-white py-2 rounded hover:bg-slate-800 transition">
                    Save Transaction
                </button>
            </form>
        </div>
    );
};

export default ExpenseEntry;
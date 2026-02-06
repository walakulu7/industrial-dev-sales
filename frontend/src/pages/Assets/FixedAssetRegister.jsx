import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaBuilding, FaPlus, FaTrash, FaCalculator } from "react-icons/fa";

const FixedAssetRegister = () => {
    const [assets, setAssets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);

    // Form Data
    const [formData, setFormData] = useState({
        name: "",
        value: "",
        purchase_date: "",
        depreciation_rate: ""
    });

    // Fetch Assets
    const fetchAssets = async () => {
        setLoading(true);
        try {
            const res = await API.get('/assets');
            setAssets(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchAssets(); }, []);

    // Handle Submit
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.post('/assets', formData);
            alert("Asset Registered Successfully");
            setFormData({ name: "", value: "", purchase_date: "", depreciation_rate: "" });
            setShowForm(false);
            fetchAssets();
        } catch (err) {
            alert(err.response?.data?.message || "Failed to add asset. Check inputs.");
        }
    };

    // Handle Delete
    const handleDelete = async (id) => {
        if (!window.confirm("Delete this asset?")) return;
        try {
            await API.delete(`/assets/${id}`);
            fetchAssets();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Error deleting asset");
        }
    };

    return (
        <div className="animate-fade-in-up">

            {/* Header */}
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaBuilding className="text-indigo-600" /> Fixed Asset Register
                    </h1>
                    <p className="text-gray-500 text-sm">Track company assets and depreciation.</p>
                </div>
                <button
                    onClick={() => setShowForm(!showForm)}
                    className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex items-center"
                >
                    <FaPlus className="mr-2" /> {showForm ? "Close Form" : "Register Asset"}
                </button>
            </div>

            {/* Entry Form */}
            {showForm && (
                <div className="bg-white p-6 rounded-lg shadow mb-6 border border-indigo-100">
                    <h3 className="font-bold text-lg mb-4 text-indigo-800">Register New Asset</h3>
                    <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="md:col-span-2">
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Asset Name</label>
                            <input type="text" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })}
                                placeholder="e.g., Industrial Loom #4" />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Value (LKR)</label>
                            <input type="number" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                value={formData.value} onChange={e => setFormData({ ...formData, value: e.target.value })}
                                placeholder="0.00" />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Purchase Date</label>
                            <input type="date" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                value={formData.purchase_date} onChange={e => setFormData({ ...formData, purchase_date: e.target.value })} />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Depreciation Rate (%)</label>
                            <input type="number" className="w-full border p-2 rounded focus:outline-indigo-500"
                                value={formData.depreciation_rate} onChange={e => setFormData({ ...formData, depreciation_rate: e.target.value })}
                                placeholder="e.g., 10" />
                        </div>

                        <div className="flex items-end">
                            <button type="submit" className="w-full bg-slate-900 text-white p-2 rounded font-bold hover:bg-slate-800">
                                Save Record
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Asset Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                        <tr>
                            <th className="p-4">Asset Name</th>
                            <th className="p-4">Purchase Date</th>
                            <th className="p-4 text-right">Original Value</th>
                            <th className="p-4 text-right">Current Value</th>
                            <th className="p-4 text-center">Status</th>
                            <th className="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="6" className="p-8 text-center text-gray-500">Loading assets...</td></tr>
                        ) : assets.length > 0 ? (
                            assets.map((item) => (
                                <tr key={item.id} className="hover:bg-indigo-50">
                                    <td className="p-4 font-bold text-slate-700">{item.name}</td>
                                    <td className="p-4 text-gray-600">{new Date(item.purchase_date).toLocaleDateString()}</td>
                                    <td className="p-4 text-right text-gray-500">{formatCurrency(item.value)}</td>
                                    <td className="p-4 text-right font-bold text-indigo-700">{formatCurrency(item.current_value)}</td>
                                    <td className="p-4 text-center">
                                        <span className="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold uppercase">{item.status}</span>
                                    </td>
                                    <td className="p-4 text-center">
                                        <button onClick={() => handleDelete(item.id)} className="text-red-400 hover:text-red-600">
                                            <FaTrash />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="6" className="p-8 text-center text-gray-500">No fixed assets recorded.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default FixedAssetRegister;

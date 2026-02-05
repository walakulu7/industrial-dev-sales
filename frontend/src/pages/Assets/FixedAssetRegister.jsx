import { useState } from "react";
import useFetch from "../../hooks/useFetch";
import API from "../../api/axiosConfig";
import { ENDPOINTS } from "../../api/endpoints";
import { useGlobal } from "../../context/GlobalState";
import { formatCurrency } from "../../utils/currencyFormat";

const FixedAssetRegister = () => {
    const { data: assets, refetch } = useFetch(ENDPOINTS.ASSETS.GET_ALL);
    const { showAlert, setIsLoading } = useGlobal();
    const [isFormOpen, setIsFormOpen] = useState(false);
    
    const [formData, setFormData] = useState({
        name: "",
        purchase_date: "",
        value: "",
        depreciation_rate: "10", // default 10%
        location_id: 1
    });

    const handleAddAsset = async (e) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            await API.post(ENDPOINTS.ASSETS.ADD, formData);
            showAlert("Asset registered successfully", "success");
            setIsFormOpen(false);
            setFormData({ name: "", purchase_date: "", value: "", depreciation_rate: "10", location_id: 1 });
            refetch(); 
        } catch (error) {
            showAlert("Failed to add asset. Check inputs.", "error");
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">Fixed Asset Register</h1>
                    <p className="text-gray-500">Track company assets and depreciation.</p>
                </div>
                <button 
                    onClick={() => setIsFormOpen(!isFormOpen)}
                    className={`px-5 py-2 rounded shadow text-white font-medium transition
                        ${isFormOpen ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-600 hover:bg-blue-700'}`}
                >
                    {isFormOpen ? "Close Form" : "+ Add New Asset"}
                </button>
            </div>

            {/* Collapsible Form */}
            {isFormOpen && (
                <div className="bg-blue-50 p-6 rounded shadow-md mb-8 border border-blue-100 animate-fade-in-down">
                    <h3 className="text-lg font-bold mb-4 text-blue-800">Register New Asset</h3>
                    <form onSubmit={handleAddAsset} className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="col-span-2">
                            <label className="block text-sm text-gray-600 mb-1">Asset Name</label>
                            <input type="text" placeholder="e.g., Industrial Loom X1" className="w-full border p-2 rounded" 
                                onChange={e => setFormData({...formData, name: e.target.value})} required />
                        </div>
                        
                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Value (LKR)</label>
                            <input type="number" placeholder="0.00" className="w-full border p-2 rounded" 
                                onChange={e => setFormData({...formData, value: e.target.value})} required />
                        </div>
                        
                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Purchase Date</label>
                            <input type="date" className="w-full border p-2 rounded" 
                                onChange={e => setFormData({...formData, purchase_date: e.target.value})} required />
                        </div>

                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Depreciation Rate (%)</label>
                            <input type="number" className="w-full border p-2 rounded" 
                                onChange={e => setFormData({...formData, depreciation_rate: e.target.value})} defaultValue="10" />
                        </div>

                        <div className="flex items-end">
                            <button type="submit" className="w-full bg-slate-900 text-white p-2 rounded hover:bg-slate-800 font-medium">Save Record</button>
                        </div>
                    </form>
                </div>
            )}

            {/* Assets Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full text-left">
                    <thead className="bg-slate-200 text-slate-700">
                        <tr>
                            <th className="p-4">Asset Name</th>
                            <th className="p-4">Purchase Date</th>
                            <th className="p-4">Original Value</th>
                            <th className="p-4">Current Value</th>
                            <th className="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {assets && assets.map((asset) => (
                            <tr key={asset.id} className="hover:bg-gray-50">
                                <td className="p-4 font-medium">{asset.name}</td>
                                <td className="p-4">{new Date(asset.purchase_date).toLocaleDateString()}</td>
                                <td className="p-4 text-gray-500">{formatCurrency(asset.original_value)}</td>
                                <td className="p-4 font-bold text-slate-800">{formatCurrency(asset.current_value)}</td>
                                <td className="p-4">
                                    <span className="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full uppercase font-bold tracking-wider">
                                        Active
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {assets && assets.length === 0 && (
                            <tr><td colSpan="5" className="p-8 text-center text-gray-400">No assets found in the register.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default FixedAssetRegister;
import { useEffect, useState, useContext } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
// FIX: Added FaBoxOpen to the imports below
import { FaIndustry, FaArrowRight, FaHistory, FaPlusCircle, FaCogs, FaBoxOpen, FaArrowLeft } from "react-icons/fa";

const ManageProduction = () => {
    const navigate = useNavigate();
    const { user } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState("new"); // 'new' or 'history'

    // Data Lists
    const [centers, setCenters] = useState([]);
    const [products, setProducts] = useState([]);
    const [warehouses, setWarehouses] = useState([]);
    const [history, setHistory] = useState([]);

    // Form Data
    const [formData, setFormData] = useState({
        center_id: "",
        date: new Date().toISOString().split('T')[0],

        // Input (Raw Material)
        input_product_id: "",
        input_warehouse_id: "",
        input_qty: "",

        // Output (Finished Good)
        output_product_id: "",
        output_warehouse_id: "",
        output_qty: ""
    });

    useEffect(() => {
        const loadData = async () => {
            try {
                const [centerRes, prodRes, whRes] = await Promise.all([
                    API.get('/production/centers'),
                    API.get('/inventory/items'),
                    API.get('/inventory/warehouses')
                ]);
                setCenters(centerRes.data);
                setProducts(prodRes.data);
                setWarehouses(whRes.data);
            } catch (err) { console.error(err); }
        };
        loadData();
    }, []);

    useEffect(() => {
        if (activeTab === 'history') {
            API.get('/production/history').then(res => setHistory(res.data));
        }
    }, [activeTab]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.post('/production/record', { ...formData, user_id: user.user.id });
            alert("Production Recorded Successfully!");
            // Reset quantities
            setFormData(prev => ({ ...prev, input_qty: "", output_qty: "" }));
        } catch (err) {
            alert(err.response?.data?.message || "Production Failed");
        }
    };

    return (
        <div className="animate-fade-in-up">
            <div className="mb-6 flex items-center justify-between">
                <button onClick={() => navigate(-1)} className="text-gray-500 hover:text-indigo-600 flex items-center gap-2 transition">
                    <FaArrowLeft /> Back
                </button>
            </div>
            <h1 className="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <FaIndustry className="text-indigo-600" /> Production Management
            </h1>

            {/* Tabs */}
            <div className="flex border-b border-gray-200 mb-6">
                <button onClick={() => setActiveTab("new")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'new' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'}`}>
                    <FaPlusCircle className="inline mr-2" /> New Production
                </button>
                <button onClick={() => setActiveTab("history")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'history' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'}`}>
                    <FaHistory className="inline mr-2" /> Production Logs
                </button>
            </div>

            {/* --- TAB 1: NEW PRODUCTION --- */}
            {activeTab === "new" && (
                <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow border border-gray-200 p-6">

                    {/* Common Details */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 border-b pb-6">
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Production Center</label>
                            <select required className="w-full border p-3 rounded bg-gray-50 focus:outline-indigo-500"
                                value={formData.center_id} onChange={e => setFormData({ ...formData, center_id: e.target.value })}>
                                <option value="">-- Select Center --</option>
                                {centers.map(c => <option key={c.production_center_id} value={c.production_center_id}>{c.center_name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Production Date</label>
                            <input type="date" required className="w-full border p-3 rounded bg-gray-50 focus:outline-indigo-500"
                                value={formData.date} onChange={e => setFormData({ ...formData, date: e.target.value })} />
                        </div>
                    </div>

                    <div className="flex flex-col md:flex-row gap-8 items-center">

                        {/* LEFT: INPUT (Raw Material) */}
                        <div className="flex-1 w-full bg-red-50 p-6 rounded-lg border border-red-100">
                            <h3 className="font-bold text-red-800 mb-4 flex items-center gap-2">
                                <FaCogs /> Input (Raw Material)
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Source Warehouse</label>
                                    <select required className="w-full border p-2 rounded bg-white"
                                        value={formData.input_warehouse_id} onChange={e => setFormData({ ...formData, input_warehouse_id: e.target.value })}>
                                        <option value="">-- Select Warehouse --</option>
                                        {warehouses.map(w => <option key={w.warehouse_id} value={w.warehouse_id}>{w.warehouse_name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Raw Material (Yarn)</label>
                                    <select required className="w-full border p-2 rounded bg-white"
                                        value={formData.input_product_id} onChange={e => setFormData({ ...formData, input_product_id: e.target.value })}>
                                        <option value="">-- Select Item --</option>
                                        {products.filter(p => p.type.includes('Yarn')).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Quantity Used</label>
                                    <input type="number" step="0.001" required className="w-full border p-2 rounded"
                                        value={formData.input_qty} onChange={e => setFormData({ ...formData, input_qty: e.target.value })} />
                                </div>
                            </div>
                        </div>

                        {/* Arrow Icon */}
                        <div className="text-gray-400 text-2xl">
                            <FaArrowRight />
                        </div>

                        {/* RIGHT: OUTPUT (Finished Good) */}
                        <div className="flex-1 w-full bg-green-50 p-6 rounded-lg border border-green-100">
                            <h3 className="font-bold text-green-800 mb-4 flex items-center gap-2">
                                <FaBoxOpen /> Output (Finished Good)
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Destination Warehouse</label>
                                    <select required className="w-full border p-2 rounded bg-white"
                                        value={formData.output_warehouse_id} onChange={e => setFormData({ ...formData, output_warehouse_id: e.target.value })}>
                                        <option value="">-- Select Warehouse --</option>
                                        {warehouses.map(w => <option key={w.warehouse_id} value={w.warehouse_id}>{w.warehouse_name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Finished Product</label>
                                    <select required className="w-full border p-2 rounded bg-white"
                                        value={formData.output_product_id} onChange={e => setFormData({ ...formData, output_product_id: e.target.value })}>
                                        <option value="">-- Select Product --</option>
                                        {products.filter(p => !p.type.includes('Yarn')).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500">Quantity Produced</label>
                                    <input type="number" step="0.001" required className="w-full border p-2 rounded"
                                        value={formData.output_qty} onChange={e => setFormData({ ...formData, output_qty: e.target.value })} />
                                </div>
                            </div>
                        </div>

                    </div>

                    <button type="submit" className="w-full bg-slate-900 text-white py-4 mt-8 rounded-lg font-bold text-lg hover:bg-slate-800 transition">
                        Confirm Production Entry
                    </button>
                </form>
            )}

            {/* --- TAB 2: HISTORY --- */}
            {activeTab === "history" && (
                <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                            <tr>
                                <th className="p-4">Date</th>
                                <th className="p-4">Center</th>
                                <th className="p-4">Input (Consumed)</th>
                                <th className="p-4">Output (Produced)</th>
                                <th className="p-4">Logged By</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-sm">
                            {history.length > 0 ? history.map((log) => (
                                <tr key={log.production_id} className="hover:bg-gray-50">
                                    <td className="p-4 text-gray-600">{new Date(log.production_date).toLocaleDateString()}</td>
                                    <td className="p-4 font-bold text-slate-700">{log.center_name}</td>
                                    <td className="p-4 text-red-600">
                                        {log.input_name} <br />
                                        <span className="text-xs font-bold">(-{log.input_qty})</span>
                                    </td>
                                    <td className="p-4 text-green-600">
                                        {log.output_name} <br />
                                        <span className="text-xs font-bold">(+{log.output_qty})</span>
                                    </td>
                                    <td className="p-4 text-gray-500 text-xs">{log.username}</td>
                                </tr>
                            )) : (
                                <tr><td colSpan="5" className="p-8 text-center text-gray-500">No production records found.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default ManageProduction;

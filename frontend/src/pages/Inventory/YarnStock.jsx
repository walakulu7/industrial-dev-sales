import { useEffect, useState, useContext } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { FaWarehouse, FaBoxOpen, FaExchangeAlt, FaHistory, FaSearch, FaPlus, FaArrowLeft } from "react-icons/fa";

const InventoryManager = () => {
    const navigate = useNavigate();
    const { user } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState("overview"); // 'overview', 'transfer', 'history'

    // Data States
    const [stock, setStock] = useState([]);
    const [warehouses, setWarehouses] = useState([]);
    const [products, setProducts] = useState([]);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(false);
    const [filter, setFilter] = useState("");

    // --- STATE 1: ADJUST STOCK MODAL (Add/Remove) ---
    const [showAdjustModal, setShowAdjustModal] = useState(false);
    const [adjustData, setAdjustData] = useState({
        product_id: "", warehouse_id: "", quantity: "", type: "receipt" // receipt (add) or adjustment (remove)
    });

    // --- STATE 2: TRANSFER FORM (Move) ---
    const [transferData, setTransferData] = useState({
        product_id: "", from_warehouse_id: "", to_warehouse_id: "", quantity: ""
    });

    // Fetch Data
    const loadData = async () => {
        setLoading(true);
        try {
            const [stockRes, warehouseRes, productRes] = await Promise.all([
                API.get('/inventory/stock'),
                API.get('/inventory/warehouses'),
                API.get('/inventory/items')
            ]);
            setStock(stockRes.data);
            setWarehouses(warehouseRes.data);
            setProducts(productRes.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const loadHistory = async () => {
        try {
            const res = await API.get('/inventory/history');
            setHistory(res.data);
        } catch (err) { console.error(err); }
    };

    useEffect(() => {
        loadData();
        if (activeTab === 'history') loadHistory();
    }, [activeTab]);

    // --- HANDLER 1: ADJUST STOCK (Add/Remove) ---
    const handleAdjustment = async (e) => {
        e.preventDefault();
        try {
            await API.post('/inventory/adjust', { ...adjustData, user_id: user.user.id });
            alert("Stock updated successfully!");
            setShowAdjustModal(false);
            setAdjustData({ product_id: "", warehouse_id: "", quantity: "", type: "receipt" });
            loadData(); // Refresh table
        } catch (err) {
            alert(err.response?.data?.error || "Failed to update stock");
        }
    };

    // --- HANDLER 2: TRANSFER STOCK (Move) ---
    const handleTransfer = async (e) => {
        e.preventDefault();
        try {
            await API.post('/inventory/transfer', { ...transferData, user_id: user.user.id });
            alert("Transfer Successful!");
            setTransferData({ product_id: "", from_warehouse_id: "", to_warehouse_id: "", quantity: "" });
            setActiveTab("overview"); // Go back to stock list
            loadData();
        } catch (err) {
            alert(err.response?.data?.message || "Transfer Failed");
        }
    };

    // Filter Logic
    const filteredStock = stock.filter(item =>
        item.product_name.toLowerCase().includes(filter.toLowerCase()) ||
        item.warehouse_name.toLowerCase().includes(filter.toLowerCase())
    );

    return (
        <div className="animate-fade-in-up">

            {/* Back Button */}
            <div className="mb-6 flex items-center justify-between">
                <button onClick={() => navigate(-1)} className="text-gray-500 hover:text-indigo-600 flex items-center gap-2 transition">
                    <FaArrowLeft /> Back
                </button>
            </div>

            {/* --- Header & Adjust Button --- */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaWarehouse className="text-indigo-600" /> Inventory Management
                </h1>

                {/* RESTORED: Adjust Stock Button */}
                <button
                    onClick={() => setShowAdjustModal(true)}
                    className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex items-center transition"
                >
                    <FaPlus className="mr-2" /> Adjust Stock
                </button>
            </div>

            {/* Navigation Tabs */}
            <div className="flex border-b border-gray-200 mb-6">
                <button onClick={() => setActiveTab("overview")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'overview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                    <FaBoxOpen className="inline mr-2" /> Current Stock
                </button>
                <button onClick={() => setActiveTab("transfer")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'transfer' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                    <FaExchangeAlt className="inline mr-2" /> Stock Transfer
                </button>
                <button onClick={() => setActiveTab("history")} className={`px-6 py-3 font-medium text-sm border-b-2 transition ${activeTab === 'history' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                    <FaHistory className="inline mr-2" /> Transactions Log
                </button>
            </div>

            {/* --- TAB 1: CURRENT STOCK --- */}
            {activeTab === "overview" && (
                <div>
                    <div className="bg-white p-4 rounded-lg shadow mb-6 border border-gray-200">
                        <div className="relative">
                            <FaSearch className="absolute left-3 top-3 text-gray-400" />
                            <input
                                type="text" placeholder="Search Inventory..."
                                className="w-full pl-10 pr-4 py-2 border rounded focus:outline-indigo-500"
                                value={filter} onChange={(e) => setFilter(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                                <tr>
                                    <th className="p-4">Product Code</th>
                                    <th className="p-4">Product Name</th>
                                    <th className="p-4">Location</th>
                                    <th className="p-4 text-right">Available</th>
                                    <th className="p-4">Unit</th>
                                    <th className="p-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 text-sm">
                                {loading ? (
                                    <tr><td colSpan="6" className="p-8 text-center text-gray-500">Loading inventory...</td></tr>
                                ) : filteredStock.length > 0 ? (
                                    filteredStock.map((item, idx) => (
                                        <tr key={idx} className="hover:bg-indigo-50">
                                            <td className="p-4 font-mono text-gray-500">{item.product_code}</td>
                                            <td className="p-4 font-bold text-slate-700">{item.product_name}</td>
                                            <td className="p-4 text-gray-600">{item.warehouse_name}</td>
                                            <td className="p-4 text-right font-bold text-lg">{parseFloat(item.quantity_available).toLocaleString()}</td>
                                            <td className="p-4 text-gray-500">{item.unit_of_measure}</td>
                                            <td className="p-4 text-center">
                                                <span className={`px-2 py-1 rounded text-xs font-bold uppercase ${item.stock_status === 'Low Stock' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}`}>
                                                    {item.stock_status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr><td colSpan="6" className="p-8 text-center text-gray-500">No stock found.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* --- TAB 2: STOCK TRANSFER FORM --- */}
            {activeTab === "transfer" && (
                <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow border border-gray-200 mt-6">
                    <h2 className="text-xl font-bold text-gray-800 mb-6 border-b pb-4">Internal Stock Transfer</h2>

                    <form onSubmit={handleTransfer} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Select Product</label>
                            <select required className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none"
                                value={transferData.product_id} onChange={e => setTransferData({ ...transferData, product_id: e.target.value })}>
                                <option value="">-- Choose Product --</option>
                                {products.map(p => (
                                    <option key={p.id} value={p.id}>{p.product_code} - {p.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-bold text-gray-600 mb-2">From (Source)</label>
                                <select required className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none bg-red-50"
                                    value={transferData.from_warehouse_id} onChange={e => setTransferData({ ...transferData, from_warehouse_id: e.target.value })}>
                                    <option value="">-- Select Source --</option>
                                    {warehouses.map(w => (
                                        <option key={w.warehouse_id} value={w.warehouse_id}>{w.warehouse_name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-bold text-gray-600 mb-2">To (Destination)</label>
                                <select required className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none bg-green-50"
                                    value={transferData.to_warehouse_id} onChange={e => setTransferData({ ...transferData, to_warehouse_id: e.target.value })}>
                                    <option value="">-- Select Destination --</option>
                                    {warehouses.map(w => (
                                        <option key={w.warehouse_id} value={w.warehouse_id}>{w.warehouse_name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-600 mb-2">Quantity to Move</label>
                            <input type="number" step="0.001" required className="w-full border p-3 rounded focus:ring-2 focus:ring-indigo-500 outline-none"
                                value={transferData.quantity} onChange={e => setTransferData({ ...transferData, quantity: e.target.value })} />
                        </div>

                        <button type="submit" className="w-full bg-indigo-600 text-white py-3 rounded font-bold hover:bg-indigo-700 transition">
                            Confirm Transfer
                        </button>
                    </form>
                </div>
            )}

            {/* --- TAB 3: TRANSACTION LOGS --- */}
            {activeTab === "history" && (
                <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                            <tr>
                                <th className="p-4">Date</th>
                                <th className="p-4">Type</th>
                                <th className="p-4">Product</th>
                                <th className="p-4">Location</th>
                                <th className="p-4 text-right">Quantity</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-sm">
                            {history.length > 0 ? history.map((log) => (
                                <tr key={log.transaction_id} className="hover:bg-gray-50">
                                    <td className="p-4 text-gray-500">{new Date(log.transaction_date).toLocaleDateString()}</td>
                                    <td className="p-4">
                                        <span className={`px-2 py-1 rounded text-xs font-bold uppercase
                                            ${log.transaction_type === 'receipt' ? 'bg-green-100 text-green-700' :
                                                log.transaction_type === 'transfer' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'}`}>
                                            {log.transaction_type}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        <div className="font-bold text-gray-700">{log.product_name}</div>
                                        <div className="text-xs text-gray-400">{log.product_code}</div>
                                    </td>
                                    <td className="p-4 text-gray-600">{log.warehouse_name}</td>
                                    <td className={`p-4 text-right font-bold ${parseFloat(log.quantity) > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                        {parseFloat(log.quantity) > 0 ? '+' : ''}{parseFloat(log.quantity).toLocaleString()}
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="5" className="p-8 text-center text-gray-500">No transaction history found.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {/* --- RESTORED MODAL: ADJUST STOCK --- */}
            {showAdjustModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-lg animate-scale-in">
                        <div className="p-4 border-b bg-gray-50 rounded-t-lg">
                            <h3 className="font-bold text-lg text-gray-800">Adjust Inventory</h3>
                        </div>
                        <form onSubmit={handleAdjustment} className="p-6 space-y-4">

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Transaction Type</label>
                                <div className="grid grid-cols-2 gap-4">
                                    <label className={`border p-3 rounded text-center cursor-pointer font-bold ${adjustData.type === 'receipt' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-white'}`}>
                                        <input type="radio" name="type" className="hidden"
                                            checked={adjustData.type === 'receipt'} onChange={() => setAdjustData({ ...adjustData, type: 'receipt' })} />
                                        Stock In (Add)
                                    </label>
                                    <label className={`border p-3 rounded text-center cursor-pointer font-bold ${adjustData.type === 'adjustment' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-white'}`}>
                                        <input type="radio" name="type" className="hidden"
                                            checked={adjustData.type === 'adjustment'} onChange={() => setAdjustData({ ...adjustData, type: 'adjustment' })} />
                                        Stock Out (Remove)
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Product</label>
                                <select required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={adjustData.product_id} onChange={e => setAdjustData({ ...adjustData, product_id: e.target.value })}>
                                    <option value="">-- Select Product --</option>
                                    {products.map(p => (
                                        <option key={p.id} value={p.id}>{p.product_code} - {p.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Warehouse / Location</label>
                                <select required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={adjustData.warehouse_id} onChange={e => setAdjustData({ ...adjustData, warehouse_id: e.target.value })}>
                                    <option value="">-- Select Warehouse --</option>
                                    {warehouses.map(w => (
                                        <option key={w.warehouse_id} value={w.warehouse_id}>{w.warehouse_name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Quantity</label>
                                <input type="number" step="0.001" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={adjustData.quantity} onChange={e => setAdjustData({ ...adjustData, quantity: e.target.value })} />
                            </div>

                            <div className="flex justify-end gap-3 mt-6">
                                <button type="button" onClick={() => setShowAdjustModal(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-indigo-600 text-white font-bold rounded hover:bg-indigo-700">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default InventoryManager;

import { useEffect, useState, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { FaClipboardList, FaPlus, FaTasks, FaCheckCircle, FaTimesCircle } from "react-icons/fa";

const ProductionOrders = () => {
    const { user } = useContext(AuthContext);
    const [orders, setOrders] = useState([]);
    const [centers, setCenters] = useState([]);
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);

    // Form State
    const [formData, setFormData] = useState({
        center_id: "",
        product_id: "",
        planned_quantity: "",
        date: new Date().toISOString().split('T')[0]
    });

    // Load Data
    const loadData = async () => {
        try {
            const [orderRes, centerRes, prodRes] = await Promise.all([
                API.get('/production/orders'),
                API.get('/production/centers'),
                API.get('/inventory/items')
            ]);
            setOrders(orderRes.data);
            setCenters(centerRes.data);
            setProducts(prodRes.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { loadData(); }, []);

    // Create Order
    const handleCreate = async (e) => {
        e.preventDefault();
        try {
            await API.post('/production/orders', { ...formData, user_id: user.user.id });
            alert("Order Created!");
            setShowModal(false);
            setFormData({ ...formData, planned_quantity: "" }); // Reset qty
            loadData();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Failed to create order");
        }
    };

    // Update Status
    const updateStatus = async (id, status) => {
        if (!window.confirm(`Mark order as ${status}?`)) return;
        try {
            await API.put(`/production/orders/${id}/status`, { status });
            loadData();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Update failed");
        }
    };

    return (
        <div className="animate-fade-in-up">

            {/* Header */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaClipboardList className="text-indigo-600" /> Production Orders
                </h1>
                <button
                    onClick={() => setShowModal(true)}
                    className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex items-center transition"
                >
                    <FaPlus className="mr-2" /> Plan New Order
                </button>
            </div>

            {/* Orders Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                        <tr>
                            <th className="p-4">Order #</th>
                            <th className="p-4">Due Date</th>
                            <th className="p-4">Center</th>
                            <th className="p-4">Product</th>
                            <th className="p-4 text-right">Planned Qty</th>
                            <th className="p-4 text-center">Status</th>
                            <th className="p-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">Loading orders...</td></tr>
                        ) : orders.length > 0 ? (
                            orders.map((order) => (
                                <tr key={order.order_id} className="hover:bg-indigo-50 transition">
                                    <td className="p-4 font-bold text-indigo-700">{order.order_number}</td>
                                    <td className="p-4 text-gray-600">{new Date(order.order_date).toLocaleDateString()}</td>
                                    <td className="p-4">{order.center_name}</td>
                                    <td className="p-4 font-medium text-slate-800">{order.product_name}</td>
                                    <td className="p-4 text-right font-bold">{parseFloat(order.planned_quantity).toLocaleString()}</td>
                                    <td className="p-4 text-center">
                                        <span className={`px-2 py-1 rounded text-xs font-bold uppercase text-white
                                            ${order.status === 'pending' ? 'bg-orange-400' :
                                                order.status === 'in_progress' ? 'bg-blue-500' :
                                                    order.status === 'completed' ? 'bg-green-500' : 'bg-red-500'}`}>
                                            {order.status.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="p-4 text-center flex justify-center gap-2">
                                        {order.status === 'pending' && (
                                            <button onClick={() => updateStatus(order.order_id, 'in_progress')} className="text-blue-600 hover:bg-blue-100 p-2 rounded" title="Start">
                                                <FaTasks />
                                            </button>
                                        )}
                                        {order.status === 'in_progress' && (
                                            <button onClick={() => updateStatus(order.order_id, 'completed')} className="text-green-600 hover:bg-green-100 p-2 rounded" title="Complete">
                                                <FaCheckCircle />
                                            </button>
                                        )}
                                        {order.status !== 'completed' && order.status !== 'cancelled' && (
                                            <button onClick={() => updateStatus(order.order_id, 'cancelled')} className="text-red-500 hover:bg-red-100 p-2 rounded" title="Cancel">
                                                <FaTimesCircle />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">No planned orders found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Create Order Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-md animate-scale-in">
                        <div className="p-4 border-b bg-gray-50 rounded-t-lg">
                            <h3 className="font-bold text-lg text-gray-800">Plan New Production</h3>
                        </div>
                        <form onSubmit={handleCreate} className="p-6 space-y-4">

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Production Center</label>
                                <select required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={formData.center_id} onChange={e => setFormData({ ...formData, center_id: e.target.value })}>
                                    <option value="">-- Select Center --</option>
                                    {centers.map(c => <option key={c.production_center_id} value={c.production_center_id}>{c.center_name}</option>)}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Target Product</label>
                                <select required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={formData.product_id} onChange={e => setFormData({ ...formData, product_id: e.target.value })}>
                                    <option value="">-- Select Product --</option>
                                    {products.filter(p => !p.type.includes('Yarn')).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Planned Quantity</label>
                                <input type="number" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={formData.planned_quantity} onChange={e => setFormData({ ...formData, planned_quantity: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Target Date</label>
                                <input type="date" required className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={formData.date} onChange={e => setFormData({ ...formData, date: e.target.value })} />
                            </div>

                            <div className="flex justify-end gap-3 mt-6">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-indigo-600 text-white font-bold rounded hover:bg-indigo-700">Create Order</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ProductionOrders;

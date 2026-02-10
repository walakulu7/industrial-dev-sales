import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { FaEdit, FaTrash, FaPlus, FaBox, FaBarcode, FaArrowLeft } from "react-icons/fa";

const ProductMaster = () => {
    const navigate = useNavigate();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEdit, setIsEdit] = useState(false);

    // Form State
    const [formData, setFormData] = useState({
        id: null,
        name: "",
        type: "Yarn",
        unit: "KG",
        cost_price: 0,
        selling_price: 0
    });

    const fetchItems = async () => {
        setLoading(true);
        try {
            const res = await API.get('/inventory/items');
            setItems(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchItems(); }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEdit) {
                await API.put(`/inventory/items/${formData.id}`, formData);
            } else {
                await API.post('/inventory/items', formData);
            }
            setShowModal(false);
            fetchItems();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Operation failed");
        }
    };

    const handleNew = () => {
        setFormData({ id: null, name: "", type: "Yarn", unit: "KG", cost_price: 0, selling_price: 0 });
        setIsEdit(false);
        setShowModal(true);
    };

    const handleEdit = (item) => {
        setFormData({
            id: item.id,
            name: item.name,
            type: item.type,
            unit: item.unit,
            cost_price: item.cost_price,
            selling_price: item.selling_price
        });
        setIsEdit(true);
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!window.confirm("Discontinue this product?")) return;
        try {
            await API.delete(`/inventory/items/${id}`);
            fetchItems();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Error deleting item");
        }
    };

    return (
        <div className="animate-fade-in-up">

            {/* Back Button */}
            <div className="mb-6 flex items-center justify-between">
                <button onClick={() => navigate(-1)} className="text-gray-500 hover:text-indigo-600 flex items-center gap-2 transition">
                    <FaArrowLeft /> Back
                </button>
            </div>
            
            {/* Header */}
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaBox className="text-indigo-600" /> Product Registry
                    </h1>
                    <p className="text-gray-500 text-sm">Manage standard costs and selling prices.</p>
                </div>
                <button onClick={handleNew} className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex items-center">
                    <FaPlus className="mr-2" /> Register Product
                </button>
            </div>

            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-slate-100 text-xs uppercase font-bold text-slate-600">
                        <tr>
                            <th className="p-4">Code</th>
                            <th className="p-4">Product Name</th>
                            <th className="p-4">Category</th>
                            <th className="p-4">Unit</th>
                            <th className="p-4 text-right">Std Cost</th>
                            <th className="p-4 text-right">Std Price</th>
                            <th className="p-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {loading ? (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">Loading products...</td></tr>
                        ) : items.length > 0 ? (
                            items.map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50">
                                    <td className="p-4 font-mono text-xs text-gray-500 flex items-center gap-2">
                                        <FaBarcode /> {item.product_code}
                                    </td>
                                    <td className="p-4 font-medium text-slate-800">{item.name}</td>
                                    <td className="p-4">
                                        <span className={`px-2 py-1 rounded text-xs font-bold ${item.type.includes('Yarn') ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600'}`}>
                                            {item.type}
                                        </span>
                                    </td>
                                    <td className="p-4 text-gray-500">{item.unit}</td>
                                    <td className="p-4 text-right text-gray-600">{parseFloat(item.cost_price).toFixed(2)}</td>
                                    <td className="p-4 text-right font-bold text-emerald-600">{parseFloat(item.selling_price).toFixed(2)}</td>
                                    <td className="p-4 text-center">
                                        <button onClick={() => handleEdit(item)} className="text-blue-400 hover:text-blue-600 mr-3"><FaEdit /></button>
                                        <button onClick={() => handleDelete(item.id)} className="text-red-400 hover:text-red-600"><FaTrash /></button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">No products registered.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50">
                    <div className="bg-white rounded-lg shadow-xl w-96 p-6">
                        <h2 className="text-xl font-bold mb-4">{isEdit ? "Edit Product" : "New Product"}</h2>
                        <form onSubmit={handleSubmit} className="space-y-3">
                            <input type="text" placeholder="Product Name" className="w-full border p-2 rounded" required
                                value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })} />

                            <div className="grid grid-cols-2 gap-3">
                                <select className="border p-2 rounded" value={formData.type} onChange={e => setFormData({ ...formData, type: e.target.value })}>
                                    <option value="Yarn">Yarn</option>
                                    <option value="Finished">Finished Good</option>
                                </select>
                                <select className="border p-2 rounded" value={formData.unit} onChange={e => setFormData({ ...formData, unit: e.target.value })}>
                                    <option value="KG">KG</option>
                                    <option value="MTR">Meters</option>
                                    <option value="PCS">Pieces</option>
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <input type="number" placeholder="Cost" className="border p-2 rounded"
                                    value={formData.cost_price} onChange={e => setFormData({ ...formData, cost_price: e.target.value })} />
                                <input type="number" placeholder="Price" className="border p-2 rounded"
                                    value={formData.selling_price} onChange={e => setFormData({ ...formData, selling_price: e.target.value })} />
                            </div>

                            <div className="flex justify-end gap-2 mt-4">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-gray-600">Cancel</button>
                                <button type="submit" className="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
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

export default ProductMaster;

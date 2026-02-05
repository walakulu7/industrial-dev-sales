import { useState, useEffect } from "react";
import API from "../../api/axiosConfig";
import { FaBuilding, FaPlus, FaEdit, FaTrash, FaMapMarkerAlt, FaPhone } from "react-icons/fa";

const Branches = () => {
    const [branches, setBranches] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [isEdit, setIsEdit] = useState(false);

    const [formData, setFormData] = useState({
        branch_id: null,
        branch_code: "",
        branch_name: "",
        branch_type: "main_office",
        location: "",
        address: "",
        contact_phone: "",
        status: "active"
    });

    // Fetch Branches
    const fetchBranches = async () => {
        setLoading(true);
        try {
            const res = await API.get('/branches');
            setBranches(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchBranches(); }, []);

    // Handle Submit
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEdit) {
                await API.put(`/branches/${formData.branch_id}`, formData);
            } else {
                await API.post('/branches', formData);
            }
            alert("Branch Saved Successfully");
            setShowModal(false);
            fetchBranches();
        } catch (err) {
            alert(err.response?.data?.message || "Operation failed");
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm("Are you sure? This cannot be undone.")) return;
        try {
            await API.delete(`/branches/${id}`);
            fetchBranches();
        } catch (err) {
            alert(err.response?.data?.message || "Delete failed");
        }
    };

    const openEdit = (branch) => {
        setFormData(branch);
        setIsEdit(true);
        setShowModal(true);
    };

    const openNew = () => {
        setFormData({
            branch_id: null,
            branch_code: "",
            branch_name: "",
            branch_type: "sales_center",
            location: "",
            address: "",
            contact_phone: "",
            status: "active"
        });
        setIsEdit(false);
        setShowModal(true);
    };

    return (
        <div className="animate-fade-in-up">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaBuilding className="text-indigo-600" /> Branch Management
                </h1>
                <button onClick={openNew} className="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex items-center">
                    <FaPlus className="mr-2" /> Add Branch
                </button>
            </div>

            {/* Branches List */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {branches.map(branch => (
                    <div key={branch.branch_id} className="bg-white rounded-lg shadow border border-gray-200 p-5 hover:shadow-md transition">
                        <div className="flex justify-between items-start mb-3">
                            <div>
                                <h3 className="font-bold text-lg text-slate-800">{branch.branch_name}</h3>
                                <span className={`text-xs px-2 py-1 rounded font-bold uppercase ${branch.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                    {branch.status}
                                </span>
                            </div>
                            <div className="bg-gray-100 p-2 rounded text-gray-500 font-mono text-xs">
                                {branch.branch_code}
                            </div>
                        </div>
                        
                        <div className="text-sm text-gray-600 space-y-2 mb-4">
                            <div className="flex items-center gap-2">
                                <FaBuilding className="text-gray-400" /> 
                                <span className="capitalize">{branch.branch_type.replace('_', ' ')}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <FaMapMarkerAlt className="text-gray-400" /> {branch.location}
                            </div>
                            <div className="flex items-center gap-2">
                                <FaPhone className="text-gray-400" /> {branch.contact_phone || "N/A"}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 border-t pt-3">
                            <button onClick={() => openEdit(branch)} className="text-blue-600 hover:bg-blue-50 p-2 rounded"><FaEdit /></button>
                            <button onClick={() => handleDelete(branch.branch_id)} className="text-red-500 hover:bg-red-50 p-2 rounded"><FaTrash /></button>
                        </div>
                    </div>
                ))}
            </div>

            {loading && <p className="text-center text-gray-500 mt-10">Loading branches...</p>}

            {/* Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-lg">
                        <div className="p-4 border-b bg-gray-50 rounded-t-lg flex justify-between items-center">
                            <h3 className="font-bold text-lg text-gray-800">{isEdit ? "Edit Branch" : "Add New Branch"}</h3>
                            <button onClick={() => setShowModal(false)} className="text-gray-500 hover:text-red-500 text-xl">&times;</button>
                        </div>
                        
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Branch Code</label>
                                    <input type="text" required className="w-full border p-2 rounded" placeholder="e.g. SC-001"
                                        value={formData.branch_code} onChange={e => setFormData({...formData, branch_code: e.target.value})} 
                                        disabled={isEdit} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                                    <select className="w-full border p-2 rounded"
                                        value={formData.branch_type} onChange={e => setFormData({...formData, branch_type: e.target.value})}>
                                        <option value="main_office">Main Office</option>
                                        <option value="sub_office">Sub Office</option>
                                        <option value="sales_center">Sales Center</option>
                                        <option value="production_center">Production Center</option>
                                        <option value="warehouse">Warehouse</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Branch Name</label>
                                <input type="text" required className="w-full border p-2 rounded"
                                    value={formData.branch_name} onChange={e => setFormData({...formData, branch_name: e.target.value})} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Location / City</label>
                                <input type="text" required className="w-full border p-2 rounded"
                                    value={formData.location} onChange={e => setFormData({...formData, location: e.target.value})} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Contact Phone</label>
                                <input type="text" className="w-full border p-2 rounded"
                                    value={formData.contact_phone} onChange={e => setFormData({...formData, contact_phone: e.target.value})} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Address</label>
                                <textarea rows="2" className="w-full border p-2 rounded"
                                    value={formData.address} onChange={e => setFormData({...formData, address: e.target.value})}></textarea>
                            </div>

                            {isEdit && (
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                                    <select className="w-full border p-2 rounded"
                                        value={formData.status} onChange={e => setFormData({...formData, status: e.target.value})}>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            )}

                            <div className="flex justify-end gap-3 mt-4">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-indigo-600 text-white font-bold rounded hover:bg-indigo-700">Save Branch</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Branches;
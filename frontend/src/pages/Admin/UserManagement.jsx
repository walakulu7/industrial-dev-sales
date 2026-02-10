import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { ROLES } from "../../utils/roleConstants";
import { FaTrash, FaUserPlus, FaBuilding, FaKey, FaArrowLeft } from "react-icons/fa";

const UserManagement = () => {
    const navigate = useNavigate();
    // --- State Management ---
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);

    // Toggle for "Add User" form
    const [showForm, setShowForm] = useState(false);

    // Form Data for Creating a User
    const [formData, setFormData] = useState({
        username: "",
        password: "",
        full_name: "",
        role: "Sales Assistant",
        branch_id: 1 // Default to Main Office (ID: 1)
    });

    // State for Password Reset Modal
    const [resetModal, setResetModal] = useState({ show: false, userId: null, username: "" });
    const [newPass, setNewPass] = useState("");

    // --- API Calls ---

    // 1. Fetch Users
    const fetchUsers = async () => {
        setLoading(true);
        try {
            const res = await API.get('/admin/users');
            setUsers(res.data);
        } catch (err) {
            console.error("Failed to load users", err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, []);

    // 2. Create New User
    const handleCreate = async (e) => {
        e.preventDefault();
        try {
            await API.post('/admin/users', formData);
            alert("User created successfully!");
            setShowForm(false); // Close form
            setFormData({ // Reset form
                username: "",
                password: "",
                full_name: "",
                role: "Sales Assistant",
                branch_id: 1
            });
            fetchUsers(); // Refresh list
        } catch (err) {
            alert(err.response?.data?.message || "Failed to create user");
        }
    };

    // 3. Delete User
    const handleDelete = async (id) => {
        if (!window.confirm("Are you sure you want to delete this user?")) return;
        try {
            await API.delete(`/admin/users/${id}`);
            fetchUsers();
        } catch (err) {
            alert(err.response?.data?.message || "Error deleting user");
        }
    };

    // 4. Reset Password (Admin Override)
    const handleResetSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.put(`/admin/users/${resetModal.userId}/reset-password`, { newPassword: newPass });
            alert(`Password for ${resetModal.username} has been reset.`);
            setResetModal({ show: false, userId: null, username: "" });
            setNewPass("");
        } catch (err) {
            alert(err.response?.data?.message || "Failed to reset password");
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
            
            {/* --- Header Section --- */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800">System Users</h1>
                <button
                    onClick={() => setShowForm(!showForm)}
                    className="bg-slate-900 text-white px-4 py-2 rounded flex items-center shadow hover:bg-slate-700 transition"
                >
                    <FaUserPlus className="mr-2" />
                    {showForm ? "Close Form" : "Add User"}
                </button>
            </div>

            {/* --- Create User Form (Conditional Render) --- */}
            {showForm && (
                <div className="bg-white p-6 rounded shadow-md mb-6 border-t-4 border-indigo-600 animate-fade-in-down">
                    <h3 className="font-bold text-lg mb-4 text-gray-700">Register New Employee</h3>
                    <form onSubmit={handleCreate} className="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Username</label>
                            <input type="text" className="w-full border p-2 rounded focus:ring focus:ring-indigo-200" required
                                value={formData.username}
                                onChange={e => setFormData({ ...formData, username: e.target.value })} />
                        </div>

                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Full Name</label>
                            <input type="text" className="w-full border p-2 rounded focus:ring focus:ring-indigo-200" required
                                value={formData.full_name}
                                onChange={e => setFormData({ ...formData, full_name: e.target.value })} />
                        </div>

                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Initial Password</label>
                            <input type="password" className="w-full border p-2 rounded focus:ring focus:ring-indigo-200" required
                                value={formData.password}
                                onChange={e => setFormData({ ...formData, password: e.target.value })} />
                        </div>

                        <div>
                            <label className="block text-sm text-gray-600 mb-1">Role</label>
                            <select className="w-full border p-2 rounded focus:ring focus:ring-indigo-200"
                                value={formData.role}
                                onChange={e => setFormData({ ...formData, role: e.target.value })}>
                                {Object.values(ROLES).map(role => (
                                    <option key={role} value={role}>{role}</option>
                                ))}
                            </select>
                        </div>

                        <div className="md:col-span-2">
                            <label className="block text-sm text-gray-600 mb-1">Branch / Location</label>
                            <select className="w-full border p-2 rounded focus:ring focus:ring-indigo-200"
                                value={formData.branch_id}
                                onChange={e => setFormData({ ...formData, branch_id: parseInt(e.target.value) })}>
                                <option value="1">Viskampiyasa Head Office</option>
                                <option value="2">Polonnaruwa Office</option>
                                {/* Add more options if you added more branches to DB */}
                            </select>
                        </div>

                        <div className="md:col-span-2 flex justify-end gap-3 mt-2">
                            <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 text-gray-500 hover:bg-gray-100 rounded">Cancel</button>
                            <button type="submit" className="px-6 py-2 bg-indigo-600 text-white font-bold rounded hover:bg-indigo-700">Create Account</button>
                        </div>
                    </form>
                </div>
            )}

            {/* --- Password Reset Modal --- */}
            {resetModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white p-6 rounded shadow-xl w-full max-w-sm animate-scale-in">
                        <h3 className="text-lg font-bold mb-2">Reset Password</h3>
                        <p className="text-sm text-gray-500 mb-4">
                            Set a new password for <span className="font-bold text-gray-800">{resetModal.username}</span>.
                        </p>
                        <form onSubmit={handleResetSubmit}>
                            <input
                                type="password"
                                placeholder="Enter new password..."
                                className="w-full border p-2 rounded mb-4 focus:outline-yellow-500"
                                value={newPass}
                                onChange={(e) => setNewPass(e.target.value)}
                                required minLength={4}
                            />
                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setResetModal({ show: false })} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-4 py-2 bg-yellow-500 text-white font-bold rounded hover:bg-yellow-600">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* --- User List Table --- */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left">
                    <thead className="bg-slate-100 border-b border-gray-200 text-xs uppercase text-slate-500 font-semibold">
                        <tr>
                            <th className="p-4">User Details</th>
                            <th className="p-4">Role</th>
                            <th className="p-4">Location</th>
                            <th className="p-4">Status</th>
                            <th className="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {loading ? (
                            <tr><td colSpan="5" className="p-8 text-center text-gray-500">Loading users...</td></tr>
                        ) : users.map(u => (
                            <tr key={u.id} className="hover:bg-gray-50 transition">
                                <td className="p-4">
                                    <div className="font-bold text-slate-800">{u.username}</div>
                                    <div className="text-sm text-gray-500">{u.full_name}</div>
                                </td>
                                <td className="p-4">
                                    <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-semibold border border-blue-200">
                                        {u.role}
                                    </span>
                                </td>
                                <td className="p-4 text-sm text-gray-600">
                                    <div className="flex items-center gap-2">
                                        <FaBuilding className="text-gray-400" />
                                        {u.branch_name || <span className="text-gray-400 italic">No Branch</span>}
                                    </div>
                                </td>
                                <td className="p-4">
                                    <span className={`text-xs font-bold uppercase ${u.status === 'active' ? 'text-green-600' : 'text-red-600'}`}>
                                        {u.status}
                                    </span>
                                </td>
                                <td className="p-4 text-right flex justify-end gap-3">
                                    <button
                                        onClick={() => setResetModal({ show: true, userId: u.id, username: u.username })}
                                        className="bg-yellow-100 text-yellow-600 p-2 rounded hover:bg-yellow-200 transition"
                                        title="Reset Password">
                                        <FaKey />
                                    </button>

                                    <button
                                        onClick={() => handleDelete(u.id)}
                                        className="bg-red-100 text-red-600 p-2 rounded hover:bg-red-200 transition"
                                        title="Delete User">
                                        <FaTrash />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default UserManagement;

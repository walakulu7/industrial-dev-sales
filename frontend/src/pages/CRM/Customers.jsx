import { useState, useEffect, useCallback } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import {
    FaUsers, FaMoneyBillWave, FaExclamationCircle, FaSearch,
    FaPlus, FaEdit, FaPhone, FaEnvelope, FaEye
} from "react-icons/fa";

const Customers = () => {
    const [customers, setCustomers] = useState([]);
    const [stats, setStats] = useState({ total: 0, sales: 0, outstanding: 0 });
    const [loading, setLoading] = useState(false);
    const [historyModal, setHistoryModal] = useState({ show: false, customerName: "", data: [] });
    const [historyLoading, setHistoryLoading] = useState(false);

    // Filters
    const [filters, setFilters] = useState({ search: "", type: "All Types", status: "Active" });

    // Modal State
    const [showModal, setShowModal] = useState(false);
    const [isEdit, setIsEdit] = useState(false);
    const [formData, setFormData] = useState({
        customer_id: null,
        name: "", type: "Retail", contact_person: "",
        phone: "", email: "", city: "", address: "",
        credit_limit: 0, status: "active"
    });

    // Fetch Data
    const fetchCustomers = useCallback(async () => {
        setLoading(true);
        try {
            const params = {
                search: filters.search,
                type: filters.type,
                status: filters.status === "Active Only" ? "active" : undefined
            };
            const res = await API.get('/customers', { params });
            const data = res.data;
            setCustomers(data);

            // Calculate Dashboard Stats from fetched data
            const totalSales = data.reduce((acc, curr) => acc + parseFloat(curr.total_sales), 0);
            const totalOutstanding = data.reduce((acc, curr) => acc + parseFloat(curr.outstanding_balance), 0);

            setStats({
                total: data.length,
                sales: totalSales,
                outstanding: totalOutstanding
            });

        } catch (err) {
            console.error("Error fetching customers", err);
        } finally {
            setLoading(false);
        }
    }, [filters]);

    useEffect(() => {
        fetchCustomers();
    }, [fetchCustomers]); // Initial load

    // Form Handlers
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEdit) {
                await API.put(`/customers/${formData.customer_id}`, formData);
            } else {
                await API.post('/customers', formData);
            }
            alert("Customer Saved Successfully");
            setShowModal(false);
            fetchCustomers();
        } catch (err) {
            // UPDATED: Show the actual error message from the backend
            console.error(err);
            alert(err.response?.data?.error || "Operation failed. Check console for details.");
        }
    };

    const openEdit = (customer) => {
        setFormData({
            customer_id: customer.customer_id,
            name: customer.customer_name,
            type: customer.customer_type,
            contact_person: customer.contact_person,
            phone: customer.phone,
            email: customer.email,
            city: customer.city,
            address: customer.address || "",
            credit_limit: customer.credit_limit,
            status: customer.status
        });
        setIsEdit(true);
        setShowModal(true);
    };

    const openNew = () => {
        setFormData({
            customer_id: null,
            name: "", type: "Retail", contact_person: "",
            phone: "", email: "", city: "", address: "",
            credit_limit: 0, status: "active"
        });
        setIsEdit(false);
        setShowModal(true);
    };

    const viewHistory = async (customer) => {
        setHistoryLoading(true);
        setHistoryModal({ show: true, customerName: customer.customer_name, data: [] });

        try {
            const res = await API.get(`/customers/${customer.customer_id}/history`);
            setHistoryModal({
                show: true,
                customerName: customer.customer_name,
                data: res.data
            });
        } catch (err) {
            console.error(err);
            alert("Failed to load history");
        } finally {
            setHistoryLoading(false);
        }
    };

    return (
        <div className="animate-fade-in-up">

            {/* --- Header Section --- */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaUsers className="text-slate-600" /> Customer Management
                </h1>
                <button
                    onClick={openNew}
                    className="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center transition"
                >
                    <FaPlus className="mr-2" /> Add Customer
                </button>
            </div>

            {/* --- Stat Cards (Blue, Green, Yellow) --- */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                {/* Total Customers */}
                <div className="bg-blue-500 text-white p-6 rounded-lg shadow-md relative overflow-hidden">
                    <h3 className="text-blue-100 font-medium mb-1">Total Customers</h3>
                    <p className="text-4xl font-bold">{stats.total}</p>
                    <p className="text-xs text-blue-100 mt-2">Active accounts</p>
                    <FaUsers className="absolute right-4 bottom-4 text-6xl text-blue-600 opacity-30" />
                </div>

                {/* Total Sales */}
                <div className="bg-emerald-600 text-white p-6 rounded-lg shadow-md relative overflow-hidden">
                    <h3 className="text-emerald-100 font-medium mb-1">Total Sales</h3>
                    <p className="text-4xl font-bold">{formatCurrency(stats.sales)}</p>
                    <p className="text-xs text-emerald-100 mt-2">All-time sales revenue</p>
                    <FaMoneyBillWave className="absolute right-4 bottom-4 text-6xl text-emerald-800 opacity-30" />
                </div>

                {/* Outstanding Balance */}
                <div className="bg-amber-400 text-white p-6 rounded-lg shadow-md relative overflow-hidden">
                    <h3 className="text-amber-100 font-medium mb-1">Outstanding Balance</h3>
                    <p className="text-4xl font-bold">{formatCurrency(stats.outstanding)}</p>
                    <p className="text-xs text-amber-100 mt-2">Credit sales pending</p>
                    <FaExclamationCircle className="absolute right-4 bottom-4 text-6xl text-amber-600 opacity-30" />
                </div>
            </div>

            {/* --- Filters & Search Bar --- */}
            <div className="bg-white p-4 rounded-lg shadow mb-6 border border-gray-200">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="md:col-span-1">
                        <label className="text-xs text-gray-500 font-bold uppercase">Search</label>
                        <input
                            type="text"
                            placeholder="Code, Name, Phone..."
                            className="w-full border p-2 rounded focus:outline-blue-500 text-sm"
                            value={filters.search}
                            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-bold uppercase">Customer Type</label>
                        <select
                            className="w-full border p-2 rounded focus:outline-blue-500 text-sm"
                            value={filters.type}
                            onChange={(e) => setFilters({ ...filters, type: e.target.value })}
                        >
                            <option>All Types</option>
                            <option value="wholesale">Wholesale</option>
                            <option value="retail">Retail</option>
                            <option value="distributor">Distributor</option>
                            <option value="manufacturer">Manufacturer</option>
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-bold uppercase">Status</label>
                        <select
                            className="w-full border p-2 rounded focus:outline-blue-500 text-sm"
                            value={filters.status}
                            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                        >
                            <option>All Status</option>
                            <option>Active Only</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                    <div className="flex items-end">
                        <button onClick={fetchCustomers} className="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 text-sm font-bold flex justify-center items-center gap-2">
                            <FaSearch /> Search
                        </button>
                    </div>
                </div>
            </div>

            {/* --- Customer List Table --- */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b border-gray-200">
                        <tr>
                            <th className="p-4">Code</th>
                            <th className="p-4">Customer Name</th>
                            <th className="p-4">Type</th>
                            <th className="p-4">Contact</th>
                            <th className="p-4">City</th>
                            <th className="p-4 text-right">Credit Limit</th>
                            <th className="p-4 text-right">Total Sales</th>
                            <th className="p-4 text-right">Outstanding</th>
                            <th className="p-4 text-center">Status</th>
                            <th className="p-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="10" className="p-8 text-center text-gray-500">Loading customers...</td></tr>
                        ) : customers.length > 0 ? (
                            customers.map((cust) => (
                                <tr key={cust.customer_id} className="hover:bg-blue-50 transition">
                                    <td className="p-4 font-bold text-gray-700">{cust.customer_code}</td>
                                    <td className="p-4">
                                        <div className="font-bold text-slate-800">{cust.customer_name}</div>
                                        <div className="text-xs text-gray-500">{cust.contact_person || "N/A"}</div>
                                    </td>
                                    <td className="p-4">
                                        <span className={`px-2 py-1 rounded text-xs font-bold text-white uppercase
                                            ${cust.customer_type === 'wholesale' ? 'bg-blue-500' :
                                                cust.customer_type === 'retail' ? 'bg-green-600' : 'bg-cyan-500'}`}>
                                            {cust.customer_type}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex items-center gap-2 text-gray-600"><FaPhone className="text-xs" /> {cust.phone || "-"}</div>
                                        {cust.email && <div className="flex items-center gap-2 text-gray-500 text-xs mt-1"><FaEnvelope className="text-xs" /> {cust.email}</div>}
                                    </td>
                                    <td className="p-4 text-gray-600">{cust.city || "-"}</td>
                                    <td className="p-4 text-right text-gray-600">{formatCurrency(cust.credit_limit)}</td>
                                    <td className="p-4 text-right font-bold text-slate-700">{formatCurrency(cust.total_sales)}</td>
                                    <td className="p-4 text-right">
                                        {cust.outstanding_balance > 0 ? (
                                            <span className="text-red-600 font-bold">{formatCurrency(cust.outstanding_balance)}</span>
                                        ) : (
                                            <span className="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">Clear</span>
                                        )}
                                    </td>
                                    <td className="p-4 text-center">
                                        <span className={`px-2 py-1 rounded text-xs font-bold uppercase text-white ${cust.status === 'active' ? 'bg-green-500' : 'bg-gray-400'}`}>
                                            {cust.status}
                                        </span>
                                    </td>
                                    <td className="p-4 text-center flex justify-center gap-2">
                                        <button
                                            onClick={() => viewHistory(cust)} // <--- Attach Function Here
                                            className="bg-cyan-100 text-cyan-600 p-2 rounded hover:bg-cyan-200"
                                            title="View History"
                                        >
                                            <FaEye />
                                        </button>
                                        <button onClick={() => openEdit(cust)} className="bg-blue-100 text-blue-600 p-2 rounded hover:bg-blue-200" title="Edit">
                                            <FaEdit />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="10" className="p-8 text-center text-gray-500">No customers found matching your filters.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* --- Add/Edit Modal --- */}
            {showModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl animate-scale-in">
                        <div className="flex justify-between items-center bg-gray-50 p-4 rounded-t-lg border-b">
                            <h3 className="text-lg font-bold text-gray-800">{isEdit ? "Edit Customer" : "Add New Customer"}</h3>
                            <button onClick={() => setShowModal(false)} className="text-gray-500 hover:text-red-500 text-2xl">&times;</button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Company / Customer Name</label>
                                <input type="text" required className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Customer Type</label>
                                <select className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.type} onChange={e => setFormData({ ...formData, type: e.target.value })}>
                                    <option value="retail">Retail</option>
                                    <option value="wholesale">Wholesale</option>
                                    <option value="distributor">Distributor</option>
                                    <option value="manufacturer">Manufacturer</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Contact Person</label>
                                <input type="text" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.contact_person} onChange={e => setFormData({ ...formData, contact_person: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                                <input type="text" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.phone} onChange={e => setFormData({ ...formData, phone: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Email Address</label>
                                <input type="email" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.email} onChange={e => setFormData({ ...formData, email: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">City</label>
                                <input type="text" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.city} onChange={e => setFormData({ ...formData, city: e.target.value })} />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Credit Limit (LKR)</label>
                                <input type="number" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.credit_limit} onChange={e => setFormData({ ...formData, credit_limit: e.target.value })} />
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Full Address</label>
                                <textarea rows="2" className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={formData.address} onChange={e => setFormData({ ...formData, address: e.target.value })}></textarea>
                            </div>

                            {isEdit && (
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Account Status</label>
                                    <select className="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={formData.status} onChange={e => setFormData({ ...formData, status: e.target.value })}>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            )}

                            <div className="md:col-span-2 flex justify-end gap-3 mt-4">
                                <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-blue-600 text-white font-bold rounded hover:bg-blue-700">
                                    {isEdit ? "Update Customer" : "Save Customer"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            {/* --- Transaction History Modal --- */}
            {historyModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl animate-scale-in flex flex-col max-h-[90vh]">
                        {/* Modal Header */}
                        <div className="flex justify-between items-center bg-cyan-600 text-white p-4 rounded-t-lg">
                            <div>
                                <h3 className="text-lg font-bold">Transaction History</h3>
                                <p className="text-sm opacity-90">{historyModal.customerName}</p>
                            </div>
                            <button onClick={() => setHistoryModal({ ...historyModal, show: false })} className="text-white hover:text-red-200 text-2xl">&times;</button>
                        </div>

                        {/* Modal Body (Scrollable) */}
                        <div className="p-6 overflow-auto">
                            {historyLoading ? (
                                <p className="text-center text-gray-500">Loading records...</p>
                            ) : historyModal.data.length > 0 ? (
                                <table className="w-full text-left border-collapse">
                                    <thead className="bg-gray-100 text-xs uppercase text-gray-600">
                                        <tr>
                                            <th className="p-3">Date</th>
                                            <th className="p-3">Invoice #</th>
                                            <th className="p-3 text-right">Amount</th>
                                            <th className="p-3 text-right">Paid</th>
                                            <th className="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="text-sm">
                                        {historyModal.data.map((inv, idx) => (
                                            <tr key={idx} className="border-b hover:bg-gray-50">
                                                <td className="p-3 text-gray-600">{new Date(inv.invoice_date).toLocaleDateString()}</td>
                                                <td className="p-3 font-medium text-blue-600">{inv.invoice_number}</td>
                                                <td className="p-3 text-right font-bold">{formatCurrency(inv.total_amount)}</td>
                                                <td className="p-3 text-right text-gray-500">{formatCurrency(inv.paid_amount)}</td>
                                                <td className="p-3 text-center">
                                                    <span className={`px-2 py-1 rounded text-xs font-bold uppercase text-white 
                                                        ${inv.status === 'paid' ? 'bg-green-500' : inv.status === 'pending' ? 'bg-orange-400' : 'bg-red-500'}`}>
                                                        {inv.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <div className="text-center p-8 text-gray-400 bg-gray-50 rounded">
                                    <p>No transaction history found for this customer.</p>
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="p-4 border-t bg-gray-50 text-right">
                            <button onClick={() => setHistoryModal({ ...historyModal, show: false })} className="px-5 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Close</button>
                        </div>
                    </div>
                </div>
            )}
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default Customers;

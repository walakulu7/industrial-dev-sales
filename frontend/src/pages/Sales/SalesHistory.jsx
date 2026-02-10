import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaFileInvoiceDollar, FaPlus, FaEye, FaSearch, FaFilter, FaArrowLeft } from "react-icons/fa";

const SalesHistory = () => {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState("");
    const navigate = useNavigate();

    // Fetch Invoices
    useEffect(() => {
        const fetchInvoices = async () => {
            try {
                const res = await API.get('/sales');
                setInvoices(res.data);
            } catch (err) {
                console.error("Error fetching sales:", err);
            } finally {
                setLoading(false);
            }
        };
        fetchInvoices();
    }, []);

    // Filter Logic
    const filteredInvoices = invoices.filter(inv =>
        inv.invoice_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
        inv.customer_name.toLowerCase().includes(searchTerm.toLowerCase())
    );

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
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaFileInvoiceDollar className="text-blue-600" /> Sales & Invoices
                </h1>
                <Link
                    to="/sales/new"
                    className="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center transition"
                >
                    <FaPlus className="mr-2" /> New Invoice
                </Link>
            </div>

            {/* Filter Bar */}
            <div className="bg-white p-4 rounded-lg shadow mb-6 border border-gray-200 flex gap-4">
                <div className="flex-1 relative">
                    <FaSearch className="absolute left-3 top-3 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search Invoice # or Customer Name..."
                        className="w-full pl-10 pr-4 py-2 border rounded focus:outline-blue-500"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
                <button className="bg-gray-100 text-gray-600 px-4 py-2 rounded border hover:bg-gray-200 flex items-center">
                    <FaFilter className="mr-2" /> Filter
                </button>
            </div>

            {/* Invoices Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                        <tr>
                            <th className="p-4">Invoice #</th>
                            <th className="p-4">Date</th>
                            <th className="p-4">Customer</th>
                            <th className="p-4">Location</th>
                            <th className="p-4 text-right">Amount</th>
                            <th className="p-4 text-center">Status</th>
                            <th className="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">Loading invoices...</td></tr>
                        ) : filteredInvoices.length > 0 ? (
                            filteredInvoices.map((inv) => (
                                <tr key={inv.invoice_id} className="hover:bg-blue-50 transition">
                                    <td className="p-4 font-bold text-blue-600">{inv.invoice_number}</td>
                                    <td className="p-4 text-gray-600">
                                        {new Date(inv.invoice_date).toLocaleDateString()}
                                    </td>
                                    <td className="p-4 font-medium text-slate-800">{inv.customer_name}</td>
                                    <td className="p-4 text-gray-500 text-xs">{inv.branch_name}</td>
                                    <td className="p-4 text-right font-bold text-slate-700">
                                        {formatCurrency(inv.total_amount)}
                                    </td>
                                    <td className="p-4 text-center">
                                        <span className={`px-2 py-1 rounded text-xs font-bold uppercase text-white 
                                            ${inv.status === 'paid' ? 'bg-green-500' : inv.status === 'pending' ? 'bg-orange-400' : 'bg-red-500'}`}>
                                            {inv.status}
                                        </span>
                                    </td>
                                    <td className="p-4 text-center">
                                        <button
                                            onClick={() => navigate(`/sales/invoice/${inv.invoice_id}`)}
                                            className="bg-blue-100 text-blue-600 p-2 rounded hover:bg-blue-200 transition"
                                            title="View Invoice"
                                        >
                                            <FaEye />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">No invoices found.</td></tr>
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

export default SalesHistory;

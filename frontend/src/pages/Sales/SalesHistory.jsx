import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaFileInvoiceDollar, FaPlus, FaEye, FaSearch, FaFilter, FaRedo, FaArrowLeft } from "react-icons/fa";

const SalesHistory = () => {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState("");
    const navigate = useNavigate();

    // Date Filter State
    const [dateRange, setDateRange] = useState({
        startDate: "",
        endDate: ""
    });

    // Fetch Invoices (Accepts optional dates)
    const fetchInvoices = async (start = "", end = "") => {
        setLoading(true);
        try {
            const params = {};
            if (start && end) {
                params.startDate = start;
                params.endDate = end;
            }

            const res = await API.get('/sales', { params });
            setInvoices(res.data);
        } catch (err) {
            console.error("Error fetching sales:", err);
        } finally {
            setLoading(false);
        }
    };

    // Initial Load
    useEffect(() => {
        fetchInvoices();
    }, []);

    // Handle Date Filter Submit
    const handleFilter = () => {
        if (dateRange.startDate && dateRange.endDate) {
            fetchInvoices(dateRange.startDate, dateRange.endDate);
        } else {
            alert("Please select both Start and End dates");
        }
    };

    // Handle Reset
    const handleReset = () => {
        setDateRange({ startDate: "", endDate: "" });
        setSearchTerm("");
        fetchInvoices(); // Load all
    };

    // Client-side Text Filter (Customer Name / Invoice #)
    const filteredInvoices = invoices.filter(inv =>
        inv.invoice_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
        inv.customer_name.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div className="animate-fade-in-up">
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <button onClick={() => navigate('/')} className="flex items-center text-gray-500 hover:text-indigo-600 mb-2 transition">
                        <FaArrowLeft className="mr-2" /> Back to Dashboard
                    </button>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaFileInvoiceDollar className="text-blue-600" /> Sales History
                    </h1>
                </div>
                <Link
                    to="/sales/new"
                    className="bg-blue-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-blue-700 flex items-center transition font-bold"
                >
                    <FaPlus className="mr-2" /> New Invoice
                </Link>
            </div>

            {/* --- Filter & Search Bar --- */}
            <div className="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
                <div className="flex flex-col md:flex-row gap-4 items-end">

                    {/* Search Input */}
                    <div className="flex-1 w-full">
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">Search</label>
                        <div className="relative">
                            <FaSearch className="absolute left-3 top-3 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search Invoice # or Customer Name..."
                                className="w-full pl-10 pr-4 py-2.5 border rounded-lg focus:outline-blue-500 bg-gray-50 focus:bg-white transition"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Date Inputs */}
                    <div>
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">From</label>
                        <input
                            type="date"
                            className="w-full md:w-40 px-3 py-2.5 border rounded-lg focus:outline-blue-500 text-sm"
                            value={dateRange.startDate}
                            onChange={(e) => setDateRange({ ...dateRange, startDate: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">To</label>
                        <input
                            type="date"
                            className="w-full md:w-40 px-3 py-2.5 border rounded-lg focus:outline-blue-500 text-sm"
                            value={dateRange.endDate}
                            onChange={(e) => setDateRange({ ...dateRange, endDate: e.target.value })}
                        />
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-2">
                        <button
                            onClick={handleFilter}
                            className="bg-slate-800 text-white px-4 py-2.5 rounded-lg hover:bg-slate-900 flex items-center font-medium transition"
                        >
                            <FaFilter className="mr-2" /> Filter
                        </button>
                        <button
                            onClick={handleReset}
                            className="bg-gray-100 text-gray-600 px-3 py-2.5 rounded-lg hover:bg-gray-200 border border-gray-300 transition"
                            title="Reset Filters"
                        >
                            <FaRedo />
                        </button>
                    </div>
                </div>
            </div>

            {/* --- Invoices Table --- */}
            <div className="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b border-gray-200">
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
                                    <tr key={inv.invoice_id} className="hover:bg-blue-50/50 transition">
                                        <td className="p-4 font-mono font-bold text-blue-600">{inv.invoice_number}</td>
                                        <td className="p-4 text-gray-600">
                                            {new Date(inv.invoice_date).toLocaleDateString()}
                                        </td>
                                        <td className="p-4 font-medium text-slate-800">{inv.customer_name}</td>
                                        <td className="p-4 text-gray-500 text-xs">{inv.branch_name}</td>
                                        <td className="p-4 text-right font-bold text-slate-700">
                                            {formatCurrency(inv.total_amount)}
                                        </td>
                                        <td className="p-4 text-center">
                                            <span className={`px-2 py-1 rounded-md text-xs font-bold uppercase tracking-wider 
                                                ${inv.status === 'paid' ? 'bg-green-100 text-green-700' : inv.status === 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'}`}>
                                                {inv.status}
                                            </span>
                                        </td>
                                        <td className="p-4 text-center">
                                            <button
                                                onClick={() => navigate(`/sales/invoice/${inv.invoice_id}`)}
                                                className="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-200 transition shadow-sm"
                                                title="View Invoice"
                                            >
                                                <FaEye />
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr><td colSpan="7" className="p-10 text-center text-gray-400 italic">No invoices found matching your filters.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default SalesHistory;

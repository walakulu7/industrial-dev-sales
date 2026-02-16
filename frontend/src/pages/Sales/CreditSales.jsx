import { useEffect, useState, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { formatCurrency } from "../../utils/currencyFormat";
import { 
    FaMoneyBillWave, FaSearch, FaExclamationTriangle, 
    FaHistory, FaPlusCircle, FaTimes, FaClipboardList, 
    FaFilter, FaRedo, FaArrowLeft
} from "react-icons/fa";
import { useNavigate } from "react-router-dom";

const CreditSales = () => {
    const { user } = useContext(AuthContext);
    const navigate = useNavigate();
    
    const [credits, setCredits] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState("");

    // Date Filter State
    const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });

    // Payment Modal State
    const [payModal, setPayModal] = useState({ 
        show: false, creditId: "", balance: 0, customer: "", isManualSelect: false 
    });
    const [paymentData, setPaymentData] = useState({ amount: "", method: "cash", reference: "" });
    
    // History Modal State
    const [historyModal, setHistoryModal] = useState({ 
        show: false, title: "", logs: [], type: 'single' 
    });

    // Fetch Data (Accepts dates)
    const loadCredits = async (start = "", end = "") => {
        setLoading(true);
        try {
            const params = {};
            if (start && end) {
                params.startDate = start;
                params.endDate = end;
            }
            const res = await API.get('/finance/credits', { params });
            setCredits(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { loadCredits(); }, []);

    // Filter Handlers
    const handleFilterDate = () => {
        if (dateRange.startDate && dateRange.endDate) {
            loadCredits(dateRange.startDate, dateRange.endDate);
        } else {
            alert("Please select both Start and End dates");
        }
    };

    const handleResetDate = () => {
        setDateRange({ startDate: "", endDate: "" });
        loadCredits();
    };

    // Handle Payment Submit
    const handlePayment = async (e) => {
        e.preventDefault();
        if(!payModal.creditId) return alert("Select invoice");
        
        try {
            await API.post('/finance/payment', {
                credit_id: payModal.creditId,
                amount: paymentData.amount,
                payment_method: paymentData.method,
                reference: paymentData.reference,
                user_id: user.user.id
            });
            alert("Payment Recorded!");
            setPayModal({ show: false, creditId: "", balance: 0, customer: "", isManualSelect: false });
            setPaymentData({ amount: "", method: "cash", reference: "" });
            loadCredits(dateRange.startDate, dateRange.endDate);
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Payment Failed");
        }
    };

    // View History Logic
    const viewHistory = async (item) => {
        try {
            const res = await API.get(`/finance/payment-history/${item.credit_id}`);
            setHistoryModal({ show: true, title: `History: ${item.customer_name}`, logs: res.data, type: 'single' });
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Failed to load history");
        }
    };

    const viewAllHistory = async () => {
        try {
            const res = await API.get('/finance/payments');
            setHistoryModal({ show: true, title: "Global Payment Logs (Recent 100)", logs: res.data, type: 'all' });
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Failed to load logs");
        }
    };

    const handleInvoiceSelect = (e) => {
        const selectedId = e.target.value;
        if (!selectedId) return;
        const selectedCredit = credits.find(c => c.credit_id == selectedId);
        if (selectedCredit) {
            setPayModal(prev => ({
                ...prev, creditId: selectedCredit.credit_id, customer: selectedCredit.customer_name, balance: parseFloat(selectedCredit.balance_amount)
            }));
            setPaymentData(prev => ({ ...prev, amount: selectedCredit.balance_amount })); 
        }
    };

    const filteredList = credits.filter(c => 
        c.customer_name.toLowerCase().includes(filter.toLowerCase()) || 
        c.invoice_number.toLowerCase().includes(filter.toLowerCase())
    );

    const totalOutstanding = credits.reduce((acc, curr) => acc + parseFloat(curr.balance_amount), 0);

    return (
        <div className="animate-fade-in-up pb-10">
            
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <button onClick={() => navigate('/')} className="flex items-center text-gray-500 hover:text-indigo-600 mb-2 transition">
                        <FaArrowLeft className="mr-2" /> Back
                    </button>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaMoneyBillWave className="text-emerald-600" /> Credit Management
                    </h1>
                </div>
                
                <div className="flex gap-3">
                    <button onClick={viewAllHistory} className="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center transition">
                        <FaClipboardList className="mr-2" /> Payment History
                    </button>
                    <button onClick={() => setPayModal({ show: true, creditId: "", balance: 0, customer: "", isManualSelect: true })} className="bg-emerald-600 text-white px-4 py-2 rounded shadow hover:bg-emerald-700 flex items-center transition">
                        <FaPlusCircle className="mr-2" /> Record Payment
                    </button>
                </div>
            </div>

            {/* Stat Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-amber-500">
                    <p className="text-xs font-bold text-gray-500 uppercase">Total Outstanding</p>
                    <p className="text-3xl font-bold text-slate-800">{formatCurrency(totalOutstanding)}</p>
                </div>
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                    <p className="text-xs font-bold text-gray-500 uppercase">Active Credit Accounts</p>
                    <p className="text-3xl font-bold text-blue-600">{credits.length}</p>
                </div>
            </div>

            {/* --- FILTER BAR (NEW) --- */}
            <div className="bg-white p-5 rounded-lg shadow mb-6 border border-gray-200">
                <div className="flex flex-col md:flex-row gap-4 items-end">
                    
                    {/* Text Search */}
                    <div className="flex-1 w-full">
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">Search</label>
                        <div className="relative">
                            <FaSearch className="absolute left-3 top-3 text-gray-400" />
                            <input 
                                type="text" placeholder="Search Customer or Invoice..." 
                                className="w-full pl-10 pr-4 py-2 border rounded focus:outline-emerald-500"
                                value={filter} onChange={(e) => setFilter(e.target.value)} 
                            />
                        </div>
                    </div>

                    {/* Date Filters */}
                    <div>
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">From Date</label>
                        <input type="date" className="w-full md:w-40 border p-2 rounded focus:outline-emerald-500 text-sm"
                            value={dateRange.startDate} onChange={e => setDateRange({...dateRange, startDate: e.target.value})} />
                    </div>
                    <div>
                        <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">To Date</label>
                        <input type="date" className="w-full md:w-40 border p-2 rounded focus:outline-emerald-500 text-sm"
                            value={dateRange.endDate} onChange={e => setDateRange({...dateRange, endDate: e.target.value})} />
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-2">
                        <button onClick={handleFilterDate} className="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-900 flex items-center font-bold">
                            <FaFilter className="mr-2" /> Filter
                        </button>
                        <button onClick={handleResetDate} className="bg-gray-100 text-gray-600 px-3 py-2 rounded hover:bg-gray-200 border">
                            <FaRedo />
                        </button>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                        <tr>
                            <th className="p-4">Invoice</th>
                            <th className="p-4">Customer</th>
                            <th className="p-4">Due Date</th>
                            <th className="p-4 text-right">Total</th>
                            <th className="p-4 text-right">Balance</th>
                            <th className="p-4 text-center">Status</th>
                            <th className="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">Loading credit records...</td></tr>
                        ) : filteredList.length > 0 ? filteredList.map((item) => (
                            <tr key={item.credit_id} className="hover:bg-gray-50">
                                <td className="p-4 font-bold text-blue-600">{item.invoice_number}</td>
                                <td className="p-4">
                                    <div className="font-bold text-slate-700">{item.customer_name}</div>
                                    <div className="text-xs text-gray-500">{item.phone}</div>
                                </td>
                                <td className="p-4 text-gray-600">{new Date(item.due_date).toLocaleDateString()}</td>
                                <td className="p-4 text-right">{formatCurrency(item.total_amount)}</td>
                                <td className="p-4 text-right font-bold text-slate-800 text-lg">{formatCurrency(item.balance_amount)}</td>
                                <td className="p-4 text-center">
                                    <span className={`px-2 py-1 rounded text-xs font-bold uppercase ${parseFloat(item.balance_amount) <= 0 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                                        {parseFloat(item.balance_amount) <= 0 ? 'Paid' : 'Pending'}
                                    </span>
                                </td>
                                <td className="p-4 text-center flex justify-center gap-2">
                                    <button onClick={() => viewHistory(item)} className="bg-blue-50 text-blue-600 p-2 rounded hover:bg-blue-100 transition">
                                        <FaHistory />
                                    </button>
                                    {parseFloat(item.balance_amount) > 0 && (
                                        <button onClick={() => {
                                            setPayModal({ show: true, creditId: item.credit_id, balance: item.balance_amount, customer: item.customer_name, isManualSelect: false });
                                            setPaymentData({ ...paymentData, amount: item.balance_amount });
                                        }} className="bg-emerald-600 text-white px-3 py-1 rounded text-xs font-bold hover:bg-emerald-700">Pay</button>
                                    )}
                                </td>
                            </tr>
                        )) : (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">No active credit records found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* --- HISTORY MODAL (Unified) --- */}
            {historyModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl animate-scale-in flex flex-col max-h-[90vh]">
                        <div className="p-4 border-b bg-gray-50 rounded-t-lg flex justify-between items-center">
                            <h3 className="font-bold text-lg text-slate-800">{historyModal.title}</h3>
                            <button onClick={() => setHistoryModal({ ...historyModal, show: false })} className="text-gray-400 hover:text-red-500 text-xl"><FaTimes /></button>
                        </div>
                        <div className="p-0 overflow-auto flex-1">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-gray-100 text-xs uppercase text-gray-600 sticky top-0">
                                    <tr>
                                        <th className="p-4">Date</th>
                                        {historyModal.type === 'all' && <><th className="p-4">Customer</th><th className="p-4">Invoice #</th></>}
                                        <th className="p-4">Method</th>
                                        <th className="p-4">Reference</th>
                                        <th className="p-4 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 text-sm">
                                    {historyModal.logs.length > 0 ? (
                                        historyModal.logs.map((log) => (
                                            <tr key={log.payment_id} className="hover:bg-gray-50">
                                                <td className="p-4 text-gray-600">{new Date(log.payment_date).toLocaleDateString()}</td>
                                                {historyModal.type === 'all' && <><td className="p-4 font-bold text-slate-700">{log.customer_name}</td><td className="p-4 text-blue-600">{log.invoice_number}</td></>}
                                                <td className="p-4 capitalize">{log.payment_method}</td>
                                                <td className="p-4 text-gray-500">{log.payment_reference || "-"}</td>
                                                <td className="p-4 text-right font-bold text-emerald-600">{formatCurrency(log.amount)}</td>
                                            </tr>
                                        ))
                                    ) : <tr><td colSpan="6" className="p-8 text-center text-gray-400">No payment records found.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* --- RECORD PAYMENT MODAL --- */}
            {payModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div className="bg-emerald-600 text-white p-4 rounded-t-lg">
                            <h3 className="font-bold text-lg">Record Payment</h3>
                            <p className="text-sm opacity-90">{payModal.customer || "Select Invoice Below"}</p>
                        </div>
                        <form onSubmit={handlePayment} className="p-6 space-y-4">
                            {payModal.isManualSelect && (
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Select Invoice</label>
                                    <select className="w-full border p-2 rounded" onChange={handleInvoiceSelect} value={payModal.creditId}>
                                        <option value="">-- Choose Invoice --</option>
                                        {credits.filter(c => parseFloat(c.balance_amount) > 0).map(c => (
                                            <option key={c.credit_id} value={c.credit_id}>{c.invoice_number} - {c.customer_name} ({formatCurrency(c.balance_amount)})</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                                <input type="number" step="0.01" max={payModal.balance} required className="w-full border p-2 rounded" value={paymentData.amount} onChange={e => setPaymentData({...paymentData, amount: e.target.value})} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Method</label>
                                    <select className="w-full border p-2 rounded" value={paymentData.method} onChange={e => setPaymentData({...paymentData, method: e.target.value})}>
                                        <option value="cash">Cash</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Ref</label>
                                    <input type="text" className="w-full border p-2 rounded" value={paymentData.reference} onChange={e => setPaymentData({...paymentData, reference: e.target.value})} />
                                </div>
                            </div>
                            <div className="flex justify-end gap-3 mt-4">
                                <button type="button" onClick={() => setPayModal({show: false})} className="px-4 py-2 text-gray-600 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-emerald-600 text-white font-bold rounded">Confirm</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

        </div>
    );
};

export default CreditSales;

import { useEffect, useState, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaMoneyBillWave, FaSearch, FaExclamationTriangle, FaHistory, FaPlusCircle } from "react-icons/fa";

const CreditSales = () => {
    const { user } = useContext(AuthContext);
    
    const [credits, setCredits] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState("");

    // Payment Modal State
    const [payModal, setPayModal] = useState({ 
        show: false, 
        creditId: "", 
        balance: 0, 
        customer: "", 
        isManualSelect: false // New flag to show dropdown
    });
    
    const [paymentData, setPaymentData] = useState({ amount: "", method: "cash", reference: "" });
    const [historyModal, setHistoryModal] = useState({ show: false, logs: [] });

    // Fetch Data
    const loadCredits = async () => {
        setLoading(true);
        try {
            const res = await API.get('/finance/credits');
            setCredits(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { loadCredits(); }, []);

    // Handle Payment Submit
    const handlePayment = async (e) => {
        e.preventDefault();
        if(!payModal.creditId) return alert("Please select an invoice first.");

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
            loadCredits();
        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || "Payment Failed");
        }
    };

    // Handle Dropdown Selection in Modal
    const handleInvoiceSelect = (e) => {
        const selectedId = e.target.value;
        if (!selectedId) return;

        const selectedCredit = credits.find(c => c.credit_id == selectedId);
        if (selectedCredit) {
            setPayModal(prev => ({
                ...prev,
                creditId: selectedCredit.credit_id,
                customer: selectedCredit.customer_name,
                balance: selectedCredit.balance_amount
            }));
            setPaymentData(prev => ({ ...prev, amount: "" })); // Reset amount
        }
    };

    // View History
    const viewHistory = async (creditId) => {
        try {
            const res = await API.get(`/finance/payment-history/${creditId}`);
            setHistoryModal({ show: true, logs: res.data });
        } catch (err) {
            console.error(err);
            alert("Failed to load history");
        }
    };

    const getDaysOverdue = (dueDate) => {
        const due = new Date(dueDate);
        const today = new Date();
        const diffTime = today - due;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays > 0 ? diffDays : 0;
    };

    const totalOutstanding = credits.reduce((acc, curr) => acc + parseFloat(curr.balance_amount), 0);
    const overdueCount = credits.filter(c => getDaysOverdue(c.due_date) > 0 && c.status !== 'paid').length;

    const filteredList = credits.filter(c => 
        c.customer_name.toLowerCase().includes(filter.toLowerCase()) || 
        c.invoice_number.toLowerCase().includes(filter.toLowerCase())
    );

    return (
        <div className="animate-fade-in-up">
            
            {/* --- Header Section with New Button --- */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaMoneyBillWave className="text-emerald-600" /> Credit Management
                </h1>
                
                {/* NEW: Record Payment Button */}
                <button 
                    onClick={() => setPayModal({ show: true, creditId: "", balance: 0, customer: "", isManualSelect: true })}
                    className="bg-emerald-600 text-white px-4 py-2 rounded shadow hover:bg-emerald-700 flex items-center transition"
                >
                    <FaPlusCircle className="mr-2" /> Record Payment
                </button>
            </div>

            {/* --- Stat Cards --- */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-amber-500">
                    <p className="text-xs font-bold text-gray-500 uppercase">Total Outstanding</p>
                    <p className="text-3xl font-bold text-slate-800">{formatCurrency(totalOutstanding)}</p>
                </div>
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
                    <p className="text-xs font-bold text-gray-500 uppercase">Overdue Invoices</p>
                    <p className="text-3xl font-bold text-red-600">{overdueCount}</p>
                </div>
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                    <p className="text-xs font-bold text-gray-500 uppercase">Total Active Credits</p>
                    <p className="text-3xl font-bold text-blue-600">{credits.length}</p>
                </div>
            </div>

            {/* --- Filters --- */}
            <div className="bg-white p-4 rounded-lg shadow mb-6 border border-gray-200">
                <div className="relative">
                    <FaSearch className="absolute left-3 top-3 text-gray-400" />
                    <input 
                        type="text" placeholder="Search Customer or Invoice..." 
                        className="w-full pl-10 pr-4 py-2 border rounded focus:outline-emerald-500"
                        value={filter} onChange={(e) => setFilter(e.target.value)}
                    />
                </div>
            </div>

            {/* --- Table --- */}
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
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">Loading credits...</td></tr>
                        ) : filteredList.length > 0 ? filteredList.map((item) => {
                            const daysOverdue = getDaysOverdue(item.due_date);
                            const isPaid = parseFloat(item.balance_amount) <= 0;
                            
                            return (
                                <tr key={item.credit_id} className="hover:bg-gray-50 transition">
                                    <td className="p-4 font-bold text-blue-600">{item.invoice_number}</td>
                                    <td className="p-4">
                                        <div className="font-bold text-slate-700">{item.customer_name}</div>
                                        <div className="text-xs text-gray-500">{item.phone}</div>
                                    </td>
                                    <td className="p-4">
                                        <div className="text-gray-600">{new Date(item.due_date).toLocaleDateString()}</div>
                                        {daysOverdue > 0 && !isPaid && (
                                            <span className="text-xs text-red-600 font-bold flex items-center gap-1">
                                                <FaExclamationTriangle /> {daysOverdue} days late
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-4 text-right text-gray-500">{formatCurrency(item.total_amount)}</td>
                                    <td className="p-4 text-right font-bold text-slate-800 text-lg">{formatCurrency(item.balance_amount)}</td>
                                    <td className="p-4 text-center">
                                        {isPaid ? (
                                            <span className="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold uppercase">Paid</span>
                                        ) : (
                                            <span className="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold uppercase">Pending</span>
                                        )}
                                    </td>
                                    <td className="p-4 text-center flex justify-center gap-2">
                                        <button onClick={() => viewHistory(item.credit_id)} className="bg-gray-100 text-gray-600 p-2 rounded hover:bg-gray-200" title="History">
                                            <FaHistory />
                                        </button>
                                        {!isPaid && (
                                            <button 
                                                onClick={() => setPayModal({ 
                                                    show: true, creditId: item.credit_id, 
                                                    balance: item.balance_amount, customer: item.customer_name, isManualSelect: false 
                                                })}
                                                className="bg-emerald-600 text-white px-3 py-1 rounded text-xs font-bold hover:bg-emerald-700"
                                            >
                                                Pay
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        }) : (
                            <tr><td colSpan="7" className="p-8 text-center text-gray-500">No active credit records found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* --- PAYMENT MODAL --- */}
            {payModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-md animate-scale-in">
                        <div className="bg-emerald-600 text-white p-4 rounded-t-lg">
                            <h3 className="font-bold text-lg">Record Payment</h3>
                            <p className="text-sm opacity-90">{payModal.customer || "Select Invoice Below"}</p>
                        </div>
                        <form onSubmit={handlePayment} className="p-6 space-y-4">
                            
                            {/* If opened from Top Button: Show Invoice Selector */}
                            {payModal.isManualSelect && (
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Select Invoice</label>
                                    <select 
                                        className="w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500"
                                        onChange={handleInvoiceSelect}
                                        value={payModal.creditId}
                                    >
                                        <option value="">-- Choose Invoice --</option>
                                        {credits.map(c => (
                                            <option key={c.credit_id} value={c.credit_id}>
                                                {c.invoice_number} - {c.customer_name} ({formatCurrency(c.balance_amount)})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Outstanding Balance</label>
                                <div className="text-xl font-bold text-slate-800">{formatCurrency(payModal.balance)}</div>
                            </div>
                            
                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Amount</label>
                                <input 
                                    type="number" step="0.01" max={payModal.balance} required 
                                    className="w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-lg"
                                    value={paymentData.amount} onChange={e => setPaymentData({...paymentData, amount: e.target.value})}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Method</label>
                                    <select className="w-full border p-2 rounded"
                                        value={paymentData.method} onChange={e => setPaymentData({...paymentData, method: e.target.value})}>
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Reference (Optional)</label>
                                    <input type="text" className="w-full border p-2 rounded" placeholder="Cheque No..."
                                        value={paymentData.reference} onChange={e => setPaymentData({...paymentData, reference: e.target.value})} />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 mt-4">
                                <button type="button" onClick={() => setPayModal({show: false})} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                <button type="submit" className="px-6 py-2 bg-emerald-600 text-white font-bold rounded hover:bg-emerald-700">Confirm Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* --- HISTORY MODAL (Unchanged) --- */}
            {historyModal.show && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-lg animate-scale-in">
                        <div className="p-4 border-b flex justify-between items-center">
                            <h3 className="font-bold text-lg text-slate-800">Payment History</h3>
                            <button onClick={() => setHistoryModal({show: false})} className="text-gray-500 hover:text-red-500">&times;</button>
                        </div>
                        <div className="p-4 max-h-96 overflow-auto">
                            {historyModal.logs.length > 0 ? (
                                <table className="w-full text-sm text-left">
                                    <thead className="text-xs text-gray-500 uppercase bg-gray-50">
                                        <tr>
                                            <th className="p-2">Date</th>
                                            <th className="p-2">Method</th>
                                            <th className="p-2 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {historyModal.logs.map((log, i) => (
                                            <tr key={i} className="border-b">
                                                <td className="p-2">{new Date(log.payment_date).toLocaleDateString()}</td>
                                                <td className="p-2 capitalize">
                                                    {log.payment_method} <br/>
                                                    <span className="text-xs text-gray-400">{log.payment_reference}</span>
                                                </td>
                                                <td className="p-2 text-right font-bold text-emerald-600">{formatCurrency(log.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="text-center text-gray-500">No payments recorded yet.</p>
                            )}
                        </div>
                    </div>
                </div>
            )}

        </div>
    );
};

export default CreditSales;
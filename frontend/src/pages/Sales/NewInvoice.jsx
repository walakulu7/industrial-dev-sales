import { useState, useEffect, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { useNavigate } from "react-router-dom";
import { FaCartPlus, FaTrash, FaCalculator, FaBoxOpen, FaArrowLeft, FaSave, FaPlus, FaSearch } from "react-icons/fa";

const NewInvoice = () => {
    const { user } = useContext(AuthContext);
    const navigate = useNavigate();

    // Data States
    const [products, setProducts] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [branches, setBranches] = useState([]); // <--- New State for Branches

    // Form States
    const [selectedCustomer, setSelectedCustomer] = useState("");
    const [customerSearch, setCustomerSearch] = useState("");
    const [showCustomerList, setShowCustomerList] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState("cash");
    const [branchId, setBranchId] = useState(""); // Default empty, set after fetch

    // Cart States
    const [cart, setCart] = useState([]);
    const [currentItem, setCurrentItem] = useState({ id: "", name: "", price: 0, quantity: 1 });

    // New Customer Modal State
    const [showCustomerModal, setShowCustomerModal] = useState(false);
    const [newCustomer, setNewCustomer] = useState({
        name: "", type: "Retail", contact_person: "",
        phone: "", email: "", city: "", address: "",
        credit_limit: 0, status: "active"
    });

    // Load Data
    useEffect(() => {
        const loadData = async () => {
            try {
                const [prodRes, custRes, branchRes] = await Promise.all([
                    API.get('/inventory/items'),
                    API.get('/customers'),
                    API.get('/branches') // <--- Fetch Branches
                ]);
                setProducts(prodRes.data);
                setCustomers(custRes.data);
                setBranches(branchRes.data);

                // Set default branch (e.g., the first one or user's assigned branch)
                if (branchRes.data.length > 0) {
                    // Optional: Try to match user's branch name if available, else default to first
                    const defaultBranch = branchRes.data.find(b => b.branch_name === user?.user?.branch) || branchRes.data[0];
                    setBranchId(defaultBranch.branch_id);
                }
            } catch (err) {
                console.error(err);
                alert(err.response?.data?.message || "Failed to load data");
            }
        };
        loadData();
    }, [user]);

    // --- CUSTOMER SEARCH LOGIC ---
    const filteredCustomers = customers.filter(c =>
        c.customer_name.toLowerCase().includes(customerSearch.toLowerCase()) ||
        c.phone?.includes(customerSearch)
    );

    const selectCustomer = (customer) => {
        setSelectedCustomer(customer.customer_id);
        setCustomerSearch(customer.customer_name);
        setShowCustomerList(false);
    };

    // --- ADD NEW CUSTOMER LOGIC ---
    const handleSaveCustomer = async (e) => {
        e.preventDefault();
        try {
            await API.post('/customers', newCustomer);
            alert("Customer Added Successfully!");
            setShowCustomerModal(false);

            const res = await API.get('/customers');
            setCustomers(res.data);
            const created = res.data.find(c => c.customer_name === newCustomer.name);
            if (created) selectCustomer(created);

            setNewCustomer({ name: "", type: "Retail", contact_person: "", phone: "", email: "", city: "", address: "", credit_limit: 0 });

        } catch (err) {
            alert(err.response?.data?.message || "Failed to add customer");
        }
    };

    // Add to Cart
    const addToCart = () => {
        if (!currentItem.id) return alert("Select product");
        if (currentItem.quantity <= 0) return alert("Qty > 0");

        const existing = cart.find(item => item.id === currentItem.id);
        if (existing) {
            setCart(cart.map(item => item.id === currentItem.id
                ? { ...item, quantity: parseFloat(item.quantity) + parseFloat(currentItem.quantity) }
                : item
            ));
        } else {
            setCart([...cart, { ...currentItem, total: currentItem.price * currentItem.quantity }]);
        }
        setCurrentItem({ id: "", name: "", price: 0, quantity: 1 });
    };

    // Remove Item
    const removeFromCart = (id) => setCart(cart.filter(item => item.id !== id));

    // Submit Invoice
    const handleCompleteSale = async () => {
        if (cart.length === 0) return alert("Cart empty");
        if (!selectedCustomer) return alert("Select customer");
        if (!branchId) return alert("Select a billing location");

        const payload = {
            customer_id: selectedCustomer,
            branch_id: branchId,
            payment_method: paymentMethod,
            items: cart,
            user_id: user.user.id
        };

        try {
            const res = await API.post('/sales/invoice', payload);
            navigate(`/sales/invoice/${res.data.invoiceId}`);
        } catch (err) {
            console.error(err);
            alert("Failed to create invoice");
        }
    };

    const grandTotal = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);

    return (
        <div className="animate-fade-in-up flex flex-col h-full max-w-7xl mx-auto">

            {/* Header */}
            <div className="mb-4 flex items-center justify-between">
                <button onClick={() => navigate('/sales')} className="text-gray-600 hover:text-indigo-600 font-medium flex items-center transition">
                    <FaArrowLeft className="mr-2" /> Back
                </button>
                <h1 className="text-xl font-bold text-slate-800 hidden md:block">New Sales Invoice</h1>
            </div>

            {/* Main Layout */}
            <div className="flex flex-col lg:flex-row gap-6 h-full pb-20 lg:pb-0">

                {/* --- LEFT: Product Entry & Cart --- */}
                <div className="w-full lg:w-2/3 flex flex-col gap-6">
                    {/* Product Selector */}
                    <div className="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                        <h2 className="text-sm font-bold text-gray-500 uppercase mb-4 flex items-center gap-2">
                            <FaCalculator /> Add Item
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div className="md:col-span-6">
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Product</label>
                                <select
                                    className="w-full p-2.5 border rounded-md focus:ring-2 focus:ring-blue-500 outline-none bg-gray-50"
                                    value={currentItem.id}
                                    onChange={(e) => {
                                        const prod = products.find(p => p.id == e.target.value);
                                        if (prod) setCurrentItem({ id: prod.id, name: prod.name, price: prod.selling_price, quantity: 1 });
                                    }}
                                >
                                    <option value="">-- Select Product --</option>
                                    {products.map(p => <option key={p.id} value={p.id}>{p.name} (LKR {p.selling_price})</option>)}
                                </select>
                            </div>

                            <div className="md:col-span-2">
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Price</label>
                                <input type="text" disabled value={currentItem.price} className="w-full p-2.5 border rounded-md bg-gray-100 text-center font-bold text-gray-600" />
                            </div>

                            <div className="md:col-span-2">
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Qty</label>
                                <input type="number" min="1" className="w-full p-2.5 border rounded-md focus:ring-2 focus:ring-blue-500 outline-none text-center font-bold"
                                    value={currentItem.quantity} onChange={(e) => setCurrentItem({ ...currentItem, quantity: e.target.value })} />
                            </div>

                            <div className="md:col-span-2">
                                <button onClick={addToCart} className="w-full bg-blue-600 text-white p-2.5 rounded-md hover:bg-blue-700 font-bold flex justify-center items-center gap-2 shadow-sm transition">
                                    <FaCartPlus /> Add
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* --- CART TABLE --- */}
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 flex-1 flex flex-col min-h-[500px]">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th className="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-wider">Product Name</th>
                                        <th className="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Price</th>
                                        <th className="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Qty</th>
                                        <th className="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Total</th>
                                        <th className="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {cart.length > 0 ? cart.map((item, idx) => (
                                        <tr key={idx} className="hover:bg-gray-50 transition-colors duration-150">
                                            <td className="py-4 px-6">
                                                <div className="font-semibold text-gray-700">{item.name}</div>
                                            </td>
                                            <td className="py-4 px-6 text-right text-gray-600">
                                                {parseFloat(item.price).toFixed(2)}
                                            </td>
                                            <td className="py-4 px-6 text-center">
                                                <span className="bg-blue-50 text-blue-700 py-1 px-3 rounded-full text-xs font-bold border border-blue-100">
                                                    {item.quantity}
                                                </span>
                                            </td>
                                            <td className="py-4 px-6 text-right font-bold text-slate-700">
                                                {(item.price * item.quantity).toFixed(2)}
                                            </td>
                                            <td className="py-4 px-6 text-center">
                                                <button onClick={() => removeFromCart(item.id)} className="text-gray-400 hover:text-red-500 transition-colors p-2">
                                                    <FaTrash />
                                                </button>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan="5" className="h-[400px]">
                                                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                                                    <div className="bg-gray-50 p-4 rounded-2xl mb-4">
                                                        <FaBoxOpen className="text-5xl opacity-20" />
                                                    </div>
                                                    <p className="text-lg font-medium text-gray-400">Cart is empty. Add products to start.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* --- RIGHT: Checkout Details --- */}
                <div className="w-full lg:w-1/3 flex flex-col gap-4">
                    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <h3 className="text-xs font-bold text-gray-500 uppercase mb-4 tracking-wider">Invoice Details</h3>

                        <div className="space-y-5">
                            {/* Customer */}
                            <div className="relative">
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Customer</label>
                                <div className="flex gap-2">
                                    <div className="relative flex-1">
                                        <FaSearch className="absolute left-3 top-3.5 text-gray-400 text-xs" />
                                        <input
                                            type="text"
                                            placeholder="Search Customer..."
                                            className="w-full pl-8 p-2.5 border rounded-md focus:ring-2 focus:ring-blue-500 outline-none text-sm"
                                            value={customerSearch}
                                            onChange={(e) => { setCustomerSearch(e.target.value); setShowCustomerList(true); setSelectedCustomer(""); }}
                                            onFocus={() => setShowCustomerList(true)}
                                        />
                                        {showCustomerList && (
                                            <div className="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto">
                                                {filteredCustomers.length > 0 ? filteredCustomers.map(c => (
                                                    <div
                                                        key={c.customer_id}
                                                        className="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-none"
                                                        onClick={() => selectCustomer(c)}
                                                    >
                                                        <div className="font-bold text-gray-700 text-sm">{c.customer_name}</div>
                                                        <div className="text-xs text-gray-500">{c.phone}</div>
                                                    </div>
                                                )) : (
                                                    <div className="p-3 text-xs text-gray-400 text-center">No results found</div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <button
                                        onClick={() => setShowCustomerModal(true)}
                                        className="bg-blue-600 text-white p-2.5 rounded-md hover:bg-blue-700 transition"
                                        title="Add New Customer"
                                    >
                                        <FaPlus />
                                    </button>
                                </div>
                            </div>

                            {/* Payment */}
                            <div>
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Payment Type</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button onClick={() => setPaymentMethod('cash')} className={`p-2 rounded-md font-bold text-sm border transition-all ${paymentMethod === 'cash' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'}`}>Cash</button>
                                    <button onClick={() => setPaymentMethod('credit')} className={`p-2 rounded-md font-bold text-sm border transition-all ${paymentMethod === 'credit' ? 'bg-orange-100 border-orange-500 text-orange-700' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'}`}>Credit</button>
                                </div>
                            </div>

                            {/* Branch Selection (UPDATED) */}
                            <div>
                                <label className="text-xs font-bold text-gray-400 mb-1 block">Billing Location</label>
                                <select
                                    className="w-full p-2.5 border rounded-md bg-white text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    value={branchId}
                                    onChange={e => setBranchId(e.target.value)}
                                >
                                    <option value="">-- Select Branch --</option>
                                    {branches.map(b => (
                                        <option key={b.branch_id} value={b.branch_id}>
                                            {b.branch_name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Footer Totals */}
                    <div className="bg-slate-900 text-white p-6 rounded-lg shadow-lg lg:relative fixed bottom-0 left-0 right-0 z-40 lg:bottom-auto m-4 lg:m-0">
                        <div className="flex justify-between items-center mb-2 text-gray-400 text-sm">
                            <span>Subtotal</span>
                            <span>LKR {grandTotal.toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between items-center mb-6 text-xl font-bold">
                            <span>Total</span>
                            <span>LKR {grandTotal.toFixed(2)}</span>
                        </div>
                        <button onClick={handleCompleteSale} className="w-full bg-green-500 text-white py-3 rounded-lg font-bold text-lg hover:bg-green-600 transition shadow-lg flex justify-center items-center gap-2">
                            <FaSave /> Complete Sale
                        </button>
                    </div>
                </div>
            </div>

            {/* --- ADD CUSTOMER MODAL (Same as before) --- */}
            {showCustomerModal && (
                <div className="fixed inset-0 bg-black/60 flex justify-center items-center z-50 p-4 backdrop-blur-sm">
                    <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl animate-scale-in flex flex-col max-h-[90vh]">
                        <div className="flex justify-between items-center bg-slate-50 p-5 rounded-t-xl border-b border-gray-100">
                            <h3 className="text-lg font-bold text-slate-800">Add New Customer</h3>
                            <button onClick={() => setShowCustomerModal(false)} className="text-gray-400 hover:text-red-500 text-2xl transition">&times;</button>
                        </div>

                        <div className="p-6 overflow-y-auto">
                            <form onSubmit={handleSaveCustomer} className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div className="md:col-span-2">
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Company / Customer Name</label>
                                    <input type="text" required className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.name} onChange={e => setNewCustomer({ ...newCustomer, name: e.target.value })} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Customer Type</label>
                                    <select className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white"
                                        value={newCustomer.type} onChange={e => setNewCustomer({ ...newCustomer, type: e.target.value })}>
                                        <option value="retail">Retail</option>
                                        <option value="wholesale">Wholesale</option>
                                        <option value="distributor">Distributor</option>
                                        <option value="manufacturer">Manufacturer</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Contact Person</label>
                                    <input type="text" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.contact_person} onChange={e => setNewCustomer({ ...newCustomer, contact_person: e.target.value })} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                                    <input type="text" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.phone} onChange={e => setNewCustomer({ ...newCustomer, phone: e.target.value })} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Email Address</label>
                                    <input type="email" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.email} onChange={e => setNewCustomer({ ...newCustomer, email: e.target.value })} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">City</label>
                                    <input type="text" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.city} onChange={e => setNewCustomer({ ...newCustomer, city: e.target.value })} />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Credit Limit (LKR)</label>
                                    <input type="number" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.credit_limit} onChange={e => setNewCustomer({ ...newCustomer, credit_limit: e.target.value })} />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Full Address</label>
                                    <textarea rows="2" className="w-full border p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        value={newCustomer.address} onChange={e => setNewCustomer({ ...newCustomer, address: e.target.value })}></textarea>
                                </div>
                                <div className="md:col-span-2 flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100">
                                    <button type="button" onClick={() => setShowCustomerModal(false)} className="px-5 py-2.5 text-gray-600 hover:bg-gray-100 rounded-lg transition font-medium">Cancel</button>
                                    <button type="submit" className="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 shadow-lg hover:shadow-blue-200 transition">Save Customer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

        </div>
    );
};

export default NewInvoice;

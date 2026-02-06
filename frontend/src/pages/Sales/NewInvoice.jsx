import { useState, useEffect, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { useNavigate } from "react-router-dom"; // Import useNavigate
import { FaCartPlus, FaTrash, FaCalculator, FaBox, FaArrowLeft } from "react-icons/fa"; // Import FaArrowLeft

const NewInvoice = () => {
    const { user } = useContext(AuthContext);
    const navigate = useNavigate(); // Initialize navigation
    
    // Data States
    const [products, setProducts] = useState([]);
    const [customers, setCustomers] = useState([]);
    
    // Form States
    const [selectedCustomer, setSelectedCustomer] = useState("");
    const [paymentMethod, setPaymentMethod] = useState("cash");
    const [branchId, setBranchId] = useState(1); // Default Viskampiyasa

    // Cart States
    const [cart, setCart] = useState([]);
    const [currentItem, setCurrentItem] = useState({ id: "", name: "", price: 0, quantity: 1 });

    // Load Data on Mount
    useEffect(() => {
        const loadData = async () => {
            try {
                const prodRes = await API.get('/inventory/items');
                setProducts(prodRes.data);
                const custRes = await API.get('/customers');
                setCustomers(custRes.data);
            } catch (err) {
                console.error("Error loading data:", err);
            }
        };
        loadData();
    }, []);

    // Handle Adding Item to Cart
    const addToCart = () => {
        if (!currentItem.id) return alert("Please select a product");
        if (currentItem.quantity <= 0) return alert("Quantity must be > 0");

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
    const removeFromCart = (id) => {
        setCart(cart.filter(item => item.id !== id));
    };

    // Submit Invoice
    const handleCompleteSale = async () => {
        if (cart.length === 0) return alert("Cart is empty");
        if (!selectedCustomer) return alert("Please select a customer");

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

    // Calculate Grand Total
    const grandTotal = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);

    return (
        <div className="animate-fade-in-up flex flex-col h-full">
            
            {/* --- TOP HEADER: Back Button --- */}
            <div className="mb-4">
                <button 
                    onClick={() => navigate('/sales')}
                    className="flex items-center text-gray-600 hover:text-indigo-600 font-medium transition"
                >
                    <FaArrowLeft className="mr-2" /> Back to Sales & Invoices
                </button>
            </div>

            {/* --- MAIN CONTENT: Columns --- */}
            <div className="flex flex-col md:flex-row gap-6 h-full pb-6">
                
                {/* --- LEFT: Invoice Creation Area --- */}
                <div className="w-full md:w-3/4 bg-white rounded-lg shadow-md flex flex-col">
                    <div className="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-lg">
                        <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <FaCalculator className="text-blue-600" /> New Sales Invoice
                        </h2>
                        <span className="text-sm text-gray-500">Date: {new Date().toLocaleDateString()}</span>
                    </div>

                    {/* Product Selection Bar */}
                    <div className="p-4 bg-blue-50 grid grid-cols-12 gap-3 items-end">
                        <div className="col-span-6">
                            <label className="text-xs font-bold text-gray-500 uppercase">Product</label>
                            <select 
                                className="w-full p-2 border rounded focus:outline-blue-500"
                                value={currentItem.id}
                                onChange={(e) => {
                                    const prod = products.find(p => p.id == e.target.value);
                                    if(prod) setCurrentItem({ 
                                        id: prod.id, 
                                        name: prod.name, 
                                        price: prod.selling_price, 
                                        quantity: 1 
                                    });
                                }}
                            >
                                <option value="">-- Select Product --</option>
                                {products.map(p => (
                                    <option key={p.id} value={p.id}>{p.product_code} - {p.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="col-span-2">
                            <label className="text-xs font-bold text-gray-500 uppercase">Price</label>
                            <input type="text" disabled value={currentItem.price} className="w-full p-2 border rounded bg-gray-100" />
                        </div>

                        <div className="col-span-2">
                            <label className="text-xs font-bold text-gray-500 uppercase">Qty</label>
                            <input 
                                type="number" min="1"
                                className="w-full p-2 border rounded focus:outline-blue-500"
                                value={currentItem.quantity}
                                onChange={(e) => setCurrentItem({ ...currentItem, quantity: e.target.value })}
                            />
                        </div>

                        <div className="col-span-2">
                            <button onClick={addToCart} className="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 font-bold flex justify-center items-center gap-2">
                                <FaCartPlus /> Add
                            </button>
                        </div>
                    </div>

                    {/* Cart Table */}
                    <div className="flex-1 overflow-auto p-4 min-h-[300px]">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-gray-100 text-xs uppercase text-gray-600 sticky top-0">
                                <tr>
                                    <th className="p-3">Product Name</th>
                                    <th className="p-3 text-right">Price</th>
                                    <th className="p-3 text-center">Qty</th>
                                    <th className="p-3 text-right">Total</th>
                                    <th className="p-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {cart.length > 0 ? cart.map((item, idx) => (
                                    <tr key={idx} className="hover:bg-gray-50">
                                        <td className="p-3 font-medium text-slate-700">{item.name}</td>
                                        <td className="p-3 text-right text-gray-600">{parseFloat(item.price).toFixed(2)}</td>
                                        <td className="p-3 text-center font-bold">{item.quantity}</td>
                                        <td className="p-3 text-right font-bold text-blue-600">{(item.price * item.quantity).toFixed(2)}</td>
                                        <td className="p-3 text-center">
                                            <button onClick={() => removeFromCart(item.id)} className="text-red-400 hover:text-red-600">
                                                <FaTrash />
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="5" className="p-10 text-center text-gray-400 flex flex-col items-center">
                                            <FaBox className="text-4xl mb-2 opacity-20" />
                                            Cart is empty. Add products to start.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* --- RIGHT: Checkout Details --- */}
                <div className="w-full md:w-1/4 bg-white rounded-lg shadow-md flex flex-col justify-between h-fit">
                    <div className="p-6 space-y-6">
                        <h3 className="text-lg font-bold text-gray-700 border-b pb-2">Invoice Details</h3>

                        {/* Customer Select */}
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Customer</label>
                            <select 
                                className="w-full p-3 border rounded bg-gray-50 focus:outline-blue-500"
                                value={selectedCustomer}
                                onChange={(e) => setSelectedCustomer(e.target.value)}
                            >
                                <option value="">-- Select Customer --</option>
                                {customers.map(c => (
                                    <option key={c.customer_id} value={c.customer_id}>{c.customer_name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Payment Method */}
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Payment Type</label>
                            <div className="grid grid-cols-2 gap-2">
                                <button 
                                    onClick={() => setPaymentMethod('cash')}
                                    className={`p-2 rounded text-sm font-bold border ${paymentMethod==='cash' ? 'bg-green-100 border-green-500 text-green-700' : 'border-gray-200 text-gray-500'}`}
                                >
                                    Cash
                                </button>
                                <button 
                                    onClick={() => setPaymentMethod('credit')}
                                    className={`p-2 rounded text-sm font-bold border ${paymentMethod==='credit' ? 'bg-orange-100 border-orange-500 text-orange-700' : 'border-gray-200 text-gray-500'}`}
                                >
                                    Credit
                                </button>
                            </div>
                        </div>

                         {/* Location */}
                         <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Billing Location</label>
                            <select className="w-full p-2 border rounded bg-gray-50 text-sm" value={branchId} onChange={e=>setBranchId(e.target.value)}>
                                <option value="1">Viskampiyasa Head Office</option>
                                <option value="2">Polonnaruwa</option>
                            </select>
                        </div>
                    </div>

                    {/* Footer Totals */}
                    <div className="p-6 bg-gray-50 border-t border-gray-200">
                        <div className="flex justify-between items-center mb-2">
                            <span className="text-gray-500">Subtotal</span>
                            <span className="font-bold">LKR {grandTotal.toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between items-center mb-6 text-xl font-bold text-slate-800">
                            <span>Total</span>
                            <span>LKR {grandTotal.toFixed(2)}</span>
                        </div>
                        
                        <button 
                            onClick={handleCompleteSale}
                            className="w-full bg-slate-900 text-white py-3 rounded-lg font-bold hover:bg-slate-800 transition shadow-lg"
                        >
                            Complete Sale
                        </button>
                    </div>
                </div>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default NewInvoice;

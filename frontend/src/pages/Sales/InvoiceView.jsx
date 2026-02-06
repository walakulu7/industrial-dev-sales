import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaPrint, FaArrowLeft } from "react-icons/fa";

const InvoiceView = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [invoice, setInvoice] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchInvoice = async () => {
            try {
                const res = await API.get(`/sales/${id}`);
                setInvoice(res.data);
            } catch (err) {
                console.error(err);
                alert(err.response?.data?.message || "Error loading invoice");
            } finally {
                setLoading(false);
            }
        };
        fetchInvoice();
    }, [id]);

    if (loading) return <div className="p-10 text-center">Loading Invoice...</div>;
    if (!invoice) return <div className="p-10 text-center">Invoice not found</div>;

    const { header, items } = invoice;

    return (
        <div className="bg-gray-100 min-h-screen p-8 print:bg-white print:p-0">
            {/* Toolbar - Hidden when printing */}
            <div className="max-w-3xl mx-auto mb-6 flex justify-between print:hidden">
                <button onClick={() => navigate('/sales/new')} className="flex items-center text-gray-600 hover:text-gray-900">
                    <FaArrowLeft className="mr-2" /> Back to Sales
                </button>
                <button onClick={() => window.print()} className="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center">
                    <FaPrint className="mr-2" /> Print Invoice
                </button>
            </div>

            {/* Invoice Paper */}
            <div className="max-w-3xl mx-auto bg-white p-10 shadow-lg rounded-lg border border-gray-200 print:shadow-none print:border-none">

                {/* Header */}
                <div className="flex justify-between items-start mb-8 border-b pb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">INVOICE</h1>
                        <p className="text-gray-500 mt-1">{header.invoice_number}</p>
                    </div>
                    <div className="text-right">
                        <h2 className="text-xl font-bold text-slate-700">Textile Management System</h2>
                        <p className="text-sm text-gray-500">{header.branch_name}</p>
                        <p className="text-sm text-gray-500">Viskampiyasa, Sri Lanka</p>
                        <p className="text-sm text-gray-500">+94 11 234 5678</p>
                    </div>
                </div>

                {/* Info Grid */}
                <div className="flex justify-between mb-8">
                    <div>
                        <p className="text-xs font-bold text-gray-400 uppercase">Bill To</p>
                        <p className="font-bold text-slate-800 text-lg">{header.customer_name}</p>
                        <p className="text-gray-600 text-sm w-48">{header.address || "No Address Provided"}</p>
                        <p className="text-gray-600 text-sm">{header.phone}</p>
                    </div>
                    <div className="text-right">
                        <div className="mb-2">
                            <p className="text-xs font-bold text-gray-400 uppercase">Date</p>
                            <p className="font-bold text-slate-700">{new Date(header.invoice_date).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <p className="text-xs font-bold text-gray-400 uppercase">Payment Method</p>
                            <p className="font-bold text-slate-700 capitalize">{header.payment_method}</p>
                        </div>
                    </div>
                </div>

                {/* Table */}
                <table className="w-full mb-8">
                    <thead>
                        <tr className="bg-gray-50 text-xs uppercase text-gray-500 border-y border-gray-200">
                            <th className="py-3 text-left">Item</th>
                            <th className="py-3 text-center">Qty</th>
                            <th className="py-3 text-right">Unit Price</th>
                            <th className="py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody className="text-sm">
                        {items.map((item, index) => (
                            <tr key={index} className="border-b border-gray-100">
                                <td className="py-3">
                                    <p className="font-bold text-slate-700">{item.product_name}</p>
                                    <p className="text-xs text-gray-400">{item.product_code}</p>
                                </td>
                                <td className="py-3 text-center">{item.quantity}</td>
                                <td className="py-3 text-right">{formatCurrency(item.unit_price)}</td>
                                <td className="py-3 text-right font-bold">{formatCurrency(item.line_total)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Totals */}
                <div className="flex justify-end mb-12">
                    <div className="w-64">
                        <div className="flex justify-between mb-2 text-gray-600">
                            <span>Subtotal</span>
                            <span>{formatCurrency(header.total_amount)}</span>
                        </div>
                        <div className="flex justify-between mb-2 text-gray-600">
                            <span>Discount</span>
                            <span>LKR 0.00</span>
                        </div>
                        <div className="flex justify-between border-t pt-2 text-xl font-bold text-slate-800">
                            <span>Total</span>
                            <span>{formatCurrency(header.total_amount)}</span>
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="border-t pt-6 text-center text-gray-500 text-sm">
                    <p className="mb-2">Thank you for your business!</p>
                    <p className="text-xs">Issued by: {header.biller_name}</p>
                </div>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default InvoiceView;

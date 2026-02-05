import { useState } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaChartBar, FaFileDownload, FaSearch, FaCalendarAlt, FaBox, FaIndustry, FaShoppingCart } from "react-icons/fa";

const ReportsHub = () => {
    const [reportType, setReportType] = useState("sales"); // sales, inventory, production
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);

    // Date Filters
    const today = new Date().toISOString().split('T')[0];
    const [dates, setDates] = useState({ start: today, end: today });

    // Generate Report
    const handleGenerate = async () => {
        setLoading(true);
        try {
            let endpoint = "";
            let params = {};

            if (reportType === 'sales') {
                endpoint = '/reports/sales';
                params = { startDate: dates.start, endDate: dates.end };
            } else if (reportType === 'inventory') {
                endpoint = '/reports/inventory'; // Inventory is a snapshot, no dates needed usually
            } else if (reportType === 'production') {
                endpoint = '/reports/production';
                params = { startDate: dates.start, endDate: dates.end };
            }

            const res = await API.get(endpoint, { params });
            setData(res.data);
        } catch (err) {
            console.error(err);
            alert("Failed to generate report");
        } finally {
            setLoading(false);
        }
    };

    // Calculate Totals for Summary Row
    const calculateTotal = (field) => {
        return data.reduce((acc, item) => acc + parseFloat(item[field] || 0), 0);
    };

    return (
        <div className="animate-fade-in-up">
            <h1 className="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <FaChartBar className="text-indigo-600" /> Reports Hub
            </h1>

            {/* --- Controls Panel --- */}
            <div className="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">

                    {/* Report Type Selector */}
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Report Type</label>
                        <select
                            className="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none font-medium"
                            value={reportType}
                            onChange={(e) => { setReportType(e.target.value); setData([]); }}
                        >
                            <option value="sales">Sales Report</option>
                            <option value="inventory">Inventory Valuation</option>
                            <option value="production">Production Log</option>
                        </select>
                    </div>

                    {/* Date Pickers (Hidden for Inventory) */}
                    {reportType !== 'inventory' && (
                        <>
                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Start Date</label>
                                <input type="date" className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={dates.start} onChange={e => setDates({ ...dates, start: e.target.value })} />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-500 uppercase mb-2">End Date</label>
                                <input type="date" className="w-full border p-2 rounded focus:outline-indigo-500"
                                    value={dates.end} onChange={e => setDates({ ...dates, end: e.target.value })} />
                            </div>
                        </>
                    )}

                    {/* Generate Button */}
                    <div className={reportType === 'inventory' ? 'md:col-span-3' : ''}>
                        <button
                            onClick={handleGenerate}
                            className="w-full bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 flex justify-center items-center font-bold"
                        >
                            <FaSearch className="mr-2" /> Generate Report
                        </button>
                    </div>
                </div>
            </div>

            {/* --- Report Results Table --- */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">

                {/* Table Header / Actions */}
                <div className="p-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 className="font-bold text-gray-700 flex items-center gap-2 capitalize">
                        {reportType === 'sales' && <FaShoppingCart />}
                        {reportType === 'inventory' && <FaBox />}
                        {reportType === 'production' && <FaIndustry />}
                        {reportType} Data Results
                    </h3>
                    {data.length > 0 && (
                        <button onClick={() => window.print()} className="text-gray-600 hover:text-indigo-600 flex items-center text-sm font-medium">
                            <FaFileDownload className="mr-2" /> Export / Print
                        </button>
                    )}
                </div>

                {/* Dynamic Table Content */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                            <tr>
                                {/* Dynamic Headers */}
                                {reportType === 'sales' && (
                                    <>
                                        <th className="p-4">Date</th>
                                        <th className="p-4">Invoice #</th>
                                        <th className="p-4">Customer</th>
                                        <th className="p-4">Branch</th>
                                        <th className="p-4 text-center">Method</th>
                                        <th className="p-4 text-right">Amount</th>
                                    </>
                                )}
                                {reportType === 'inventory' && (
                                    <>
                                        <th className="p-4">Code</th>
                                        <th className="p-4">Item Name</th>
                                        <th className="p-4">Category</th>
                                        <th className="p-4 text-right">Qty</th>
                                        <th className="p-4 text-right">Cost</th>
                                        <th className="p-4 text-right">Total Value</th>
                                    </>
                                )}
                                {reportType === 'production' && (
                                    <>
                                        <th className="p-4">Date</th>
                                        <th className="p-4">Center</th>
                                        <th className="p-4">Input Material</th>
                                        <th className="p-4 text-right">In Qty</th>
                                        <th className="p-4">Output Product</th>
                                        <th className="p-4 text-right">Out Qty</th>
                                    </>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-sm">
                            {loading ? (
                                <tr><td colSpan="6" className="p-8 text-center text-gray-500">Generating report...</td></tr>
                            ) : data.length > 0 ? (
                                data.map((row, idx) => (
                                    <tr key={idx} className="hover:bg-indigo-50">

                                        {/* Sales Rows */}
                                        {reportType === 'sales' && (
                                            <>
                                                <td className="p-4">{new Date(row.invoice_date).toLocaleDateString()}</td>
                                                <td className="p-4 font-mono text-indigo-600">{row.invoice_number}</td>
                                                <td className="p-4">{row.customer_name}</td>
                                                <td className="p-4 text-gray-500">{row.branch_name}</td>
                                                <td className="p-4 text-center capitalize">{row.payment_method}</td>
                                                <td className="p-4 text-right font-bold">{formatCurrency(row.total_amount)}</td>
                                            </>
                                        )}

                                        {/* Inventory Rows */}
                                        {reportType === 'inventory' && (
                                            <>
                                                <td className="p-4 font-mono text-gray-500">{row.product_code}</td>
                                                <td className="p-4 font-bold text-slate-700">{row.product_name}</td>
                                                <td className="p-4">{row.category_name}</td>
                                                <td className="p-4 text-right">{parseFloat(row.quantity_on_hand).toLocaleString()} {row.unit_of_measure}</td>
                                                <td className="p-4 text-right text-gray-500">{formatCurrency(row.standard_cost)}</td>
                                                <td className="p-4 text-right font-bold text-green-600">{formatCurrency(row.total_value)}</td>
                                            </>
                                        )}

                                        {/* Production Rows */}
                                        {reportType === 'production' && (
                                            <>
                                                <td className="p-4">{new Date(row.production_date).toLocaleDateString()}</td>
                                                <td className="p-4 font-bold">{row.center_name}</td>
                                                <td className="p-4 text-red-600">{row.input_material}</td>
                                                <td className="p-4 text-right font-bold">{row.input_qty}</td>
                                                <td className="p-4 text-green-600">{row.output_product}</td>
                                                <td className="p-4 text-right font-bold">{row.output_qty}</td>
                                            </>
                                        )}
                                    </tr>
                                ))
                            ) : (
                                <tr><td colSpan="6" className="p-8 text-center text-gray-500">No records found for selected period.</td></tr>
                            )}
                        </tbody>

                        {/* Footer Summary Row */}
                        {data.length > 0 && reportType !== 'production' && (
                            <tfoot className="bg-gray-100 font-bold border-t border-gray-300">
                                <tr>
                                    <td colSpan={reportType === 'sales' ? 5 : 5} className="p-4 text-right uppercase">Total:</td>
                                    <td className="p-4 text-right text-indigo-700">
                                        {formatCurrency(calculateTotal(reportType === 'sales' ? 'total_amount' : 'total_value'))}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </div>
    );
};

export default ReportsHub;
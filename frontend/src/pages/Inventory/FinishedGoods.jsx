import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";
import { FaBoxOpen, FaStore, FaSearch } from "react-icons/fa";

const FinishedGoods = () => {
    const [stock, setStock] = useState([]);
    const [loading, setLoading] = useState(true);
    const [branches, setBranches] = useState([]);
    const [selectedBranch, setSelectedBranch] = useState("All");
    const [searchTerm, setSearchTerm] = useState("");

    // Fetch Data
    useEffect(() => {
        const loadStock = async () => {
            try {
                const res = await API.get('/inventory/finished-goods');
                setStock(res.data);

                // Extract unique branches for the dropdown
                const uniqueBranches = [...new Set(res.data.map(item => item.branch_name))];
                setBranches(uniqueBranches);
            } catch (err) {
                console.error("Error loading finished goods:", err);
            } finally {
                setLoading(false);
            }
        };
        loadStock();
    }, []);

    // Filter Logic
    const filteredStock = stock.filter(item => {
        const matchesBranch = selectedBranch === "All" || item.branch_name === selectedBranch;
        const matchesSearch = item.product_name.toLowerCase().includes(searchTerm.toLowerCase());
        return matchesBranch && matchesSearch;
    });

    // Calculate Grand Total for the filtered view
    const grandTotalValue = filteredStock.reduce((acc, item) => acc + (item.quantity_on_hand * item.unit_price), 0);

    return (
        <div className="animate-fade-in-up">

            {/* Header & Controls */}
            <div className="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <FaBoxOpen className="text-indigo-600" /> Finished Goods Inventory
                    </h1>
                    <p className="text-gray-500 text-sm">Stock levels at sales centers and showrooms.</p>
                </div>

                <div className="flex gap-4 items-center bg-white p-2 rounded shadow-sm border border-gray-200">
                    <div className="flex items-center text-gray-500 px-2">
                        <FaStore className="mr-2" /> Sales Center:
                    </div>
                    <select
                        className="border-none focus:ring-0 text-slate-700 font-medium bg-transparent outline-none"
                        value={selectedBranch}
                        onChange={(e) => setSelectedBranch(e.target.value)}
                    >
                        <option value="All">All Locations</option>
                        {branches.map((branch, idx) => (
                            <option key={idx} value={branch}>{branch}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Search Bar */}
            <div className="mb-4">
                <div className="relative max-w-md">
                    <FaSearch className="absolute left-3 top-3 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search products..."
                        className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-indigo-500 shadow-sm"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
            </div>

            {/* Data Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-slate-800 text-white text-sm uppercase">
                        <tr>
                            <th className="p-4">Product Name</th>
                            <th className="p-4 text-right">Unit Price</th>
                            <th className="p-4 text-center">Current Stock</th>
                            <th className="p-4 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="4" className="p-8 text-center text-gray-500">Loading stock...</td></tr>
                        ) : filteredStock.length > 0 ? (
                            filteredStock.map((item) => (
                                <tr key={item.inventory_id} className="hover:bg-slate-50 transition">
                                    <td className="p-4">
                                        <div className="font-bold text-slate-700">{item.product_name}</div>
                                        <div className="text-xs text-gray-500">{item.warehouse_name}</div>
                                    </td>
                                    <td className="p-4 text-right text-gray-600">
                                        {formatCurrency(item.unit_price)}
                                    </td>
                                    <td className="p-4 text-center font-bold text-lg text-indigo-700">
                                        {parseFloat(item.quantity_on_hand).toLocaleString()}
                                    </td>
                                    <td className="p-4 text-right font-bold text-slate-800">
                                        {formatCurrency(item.quantity_on_hand * item.unit_price)}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="4" className="p-8 text-center text-gray-500">No finished goods found.</td></tr>
                        )}
                    </tbody>

                    {/* Footer Total */}
                    {filteredStock.length > 0 && (
                        <tfoot className="bg-gray-100 border-t-2 border-gray-300">
                            <tr>
                                <td colSpan="3" className="p-4 text-right font-bold text-gray-600 uppercase">Total Inventory Value:</td>
                                <td className="p-4 text-right font-bold text-green-700 text-lg">
                                    {formatCurrency(grandTotalValue)}
                                </td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
            <div className="text-center text-gray-400 text-sm mt-10">
                &copy; 2026 Textile Management System. All rights reserved.
            </div>
        </div>
    );
};

export default FinishedGoods;

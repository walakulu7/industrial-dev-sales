import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { FaHistory, FaSearch, FaFileDownload, FaIndustry } from "react-icons/fa";

const ProductionHistory = () => {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState("");

    // Fetch Data
    useEffect(() => {
        const fetchHistory = async () => {
            try {
                const res = await API.get('/production/history');
                setHistory(res.data);
            } catch (err) {
                console.error("Error loading history:", err);
            } finally {
                setLoading(false);
            }
        };
        fetchHistory();
    }, []);

    // Filter Data
    const filteredHistory = history.filter(item => 
        item.center_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.input_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.output_name?.toLowerCase().includes(searchTerm.toLowerCase())
    );

    // --- NEW: Export to CSV Function ---
    const handleExport = () => {
        if (filteredHistory.length === 0) return alert("No data to export");

        // 1. Define CSV Headers
        const headers = ["Date,Production Center,Input Material,Input Qty,Output Product,Output Qty,Logged By"];

        // 2. Map Data to Rows
        const rows = filteredHistory.map(log => {
            const date = new Date(log.production_date).toLocaleDateString();
            // Escape commas in names to prevent CSV breakage
            const center = `"${log.center_name}"`;
            const input = `"${log.input_name}"`;
            const output = `"${log.output_name}"`;
            const user = `"${log.username}"`;
            
            return `${date},${center},${input},${log.input_qty},${output},${log.output_qty},${user}`;
        });

        // 3. Combine and Blob
        const csvContent = [headers, ...rows].join("\n");
        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        
        // 4. Trigger Download
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `Production_Report_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    return (
        <div className="animate-fade-in-up">
            
            {/* Header */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <FaHistory className="text-indigo-600" /> Production History
                </h1>
                
                {/* Export Button with OnClick */}
                <button 
                    onClick={handleExport}
                    className="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50 flex items-center transition"
                >
                    <FaFileDownload className="mr-2 text-green-600" /> Export CSV
                </button>
            </div>

            {/* Search Bar */}
            <div className="bg-white p-4 rounded-lg shadow mb-6 border border-gray-200">
                <div className="relative">
                    <FaSearch className="absolute left-3 top-3 text-gray-400" />
                    <input 
                        type="text" 
                        placeholder="Search by Center, Material or Product..." 
                        className="w-full pl-10 pr-4 py-2 border rounded focus:outline-indigo-500"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
            </div>

            {/* Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-100 text-xs font-bold text-gray-600 uppercase border-b">
                        <tr>
                            <th className="p-4">Date</th>
                            <th className="p-4">Production Center</th>
                            <th className="p-4">Input (Raw Material)</th>
                            <th className="p-4">Output (Finished Good)</th>
                            <th className="p-4 text-center">Efficiency</th>
                            <th className="p-4 text-right">Logged By</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm">
                        {loading ? (
                            <tr><td colSpan="6" className="p-8 text-center text-gray-500">Loading records...</td></tr>
                        ) : filteredHistory.length > 0 ? (
                            filteredHistory.map((log) => (
                                <tr key={log.production_id} className="hover:bg-indigo-50 transition">
                                    <td className="p-4 whitespace-nowrap text-gray-600">
                                        {new Date(log.production_date).toLocaleDateString()}
                                    </td>
                                    
                                    <td className="p-4">
                                        <div className="font-bold text-slate-700 flex items-center gap-2">
                                            <FaIndustry className="text-gray-400" />
                                            {log.center_name}
                                        </div>
                                    </td>

                                    {/* Input Column */}
                                    <td className="p-4">
                                        <div className="flex items-center justify-between bg-red-50 p-2 rounded border border-red-100 max-w-[200px]">
                                            <span className="text-red-800 font-medium truncate w-24" title={log.input_name}>
                                                {log.input_name}
                                            </span>
                                            <span className="text-xs font-bold text-red-600 bg-white px-2 py-1 rounded shadow-sm">
                                                -{parseFloat(log.input_qty)}
                                            </span>
                                        </div>
                                    </td>

                                    {/* Output Column */}
                                    <td className="p-4">
                                        <div className="flex items-center justify-between bg-green-50 p-2 rounded border border-green-100 max-w-[200px]">
                                            <span className="text-green-800 font-medium truncate w-24" title={log.output_name}>
                                                {log.output_name}
                                            </span>
                                            <span className="text-xs font-bold text-green-600 bg-white px-2 py-1 rounded shadow-sm">
                                                +{parseFloat(log.output_qty)}
                                            </span>
                                        </div>
                                    </td>

                                    <td className="p-4 text-center">
                                        <span className="text-xs font-bold text-gray-400">
                                            1 : {(log.output_qty / log.input_qty).toFixed(2)}
                                        </span>
                                    </td>

                                    <td className="p-4 text-right text-gray-500 text-xs">
                                        {log.username}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="6" className="p-10 text-center text-gray-400 italic">No production history found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default ProductionHistory;

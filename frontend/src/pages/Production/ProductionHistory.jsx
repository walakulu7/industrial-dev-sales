import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { formatDate } from "../../utils/dateFormat";

const ProductionHistory = () => {
    const [logs, setLogs] = useState([]);

    useEffect(() => {
        API.get('/production/history').then(res => setLogs(res.data));
    }, []);

    return (
        <div>
            <h2 className="text-2xl font-bold mb-6">Production History</h2>
            <div className="bg-white shadow rounded overflow-hidden">
                <table className="w-full text-left">
                    <thead className="bg-gray-200">
                        <tr>
                            <th className="p-3">Date</th>
                            <th className="p-3">Location</th>
                            <th className="p-3">Line</th>
                            <th className="p-3">Input (Yarn)</th>
                            <th className="p-3">Output (Product)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.map((log) => (
                            <tr key={log.id} className="border-b hover:bg-gray-50">
                                <td className="p-3">{formatDate(log.date)}</td>
                                <td className="p-3">{log.location}</td>
                                <td className="p-3 text-sm text-gray-500">{log.production_line}</td>
                                <td className="p-3 text-red-600">
                                    {log.used_qty} kg <br/>
                                    <span className="text-xs text-gray-500">{log.yarn_name}</span>
                                </td>
                                <td className="p-3 text-green-600">
                                    {log.produced_qty} Units <br/>
                                    <span className="text-xs text-gray-500">{log.product_name}</span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default ProductionHistory;
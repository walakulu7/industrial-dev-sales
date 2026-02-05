import { useEffect, useState } from "react";
import API from "../../api/axiosConfig";
import { formatCurrency } from "../../utils/currencyFormat";

const FinishedGoods = () => {
    const [location, setLocation] = useState("Viskampiyasa");
    const [stock, setStock] = useState([]);

    useEffect(() => {
        API.get(`/inventory/products/stock?location=${location}_Sales_Center`) // Adjust location key based on DB
           .then(res => setStock(res.data))
           .catch(err => console.error(err));
    }, [location]);

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-bold">Finished Goods Inventory</h2>
                <div className="flex items-center gap-2">
                    <label className="font-medium">Sales Center:</label>
                    <select className="border p-2 rounded bg-white" value={location} onChange={(e) => setLocation(e.target.value)}>
                        <option value="Viskampiyasa">Viskampiyasa</option>
                        <option value="Polonnaruwa">Polonnaruwa</option>
                    </select>
                </div>
            </div>

            <div className="bg-white shadow rounded overflow-hidden">
                <table className="w-full text-left">
                    <thead className="bg-slate-800 text-white">
                        <tr>
                            <th className="p-3">Product Name</th>
                            <th className="p-3">Unit Price</th>
                            <th className="p-3">Current Stock</th>
                            <th className="p-3">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {stock.map((item) => (
                            <tr key={item.id} className="border-b hover:bg-gray-50">
                                <td className="p-3 font-medium">{item.name}</td>
                                <td className="p-3">{formatCurrency(item.selling_price)}</td>
                                <td className="p-3">{item.quantity}</td>
                                <td className="p-3 font-bold">{formatCurrency(item.quantity * item.selling_price)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default FinishedGoods;
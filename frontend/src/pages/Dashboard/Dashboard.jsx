import { useContext } from "react";
import { Link } from "react-router-dom"; // Import Link
import { AuthContext } from "../../context/AuthContext";
import DirectorDashboard from "./DirectorDashboard";
import AccountantDashboard from "./AccountantDashboard";
import { ROLES } from "../../utils/roleConstants";
import AdminDashboard from "./AdminDashboard";

const Dashboard = () => {
    const { user } = useContext(AuthContext);
    const role = user?.user?.role;

    // Render logic based on Role
    if (role === ROLES.ADMIN) {
        return <AdminDashboard />;
    }
    
    if (role === ROLES.DIRECTOR || role === ROLES.PROVINCIAL_DIRECTOR) {
        return <DirectorDashboard />;
    }

    if (role === ROLES.ACCOUNTANT) {
        return <AccountantDashboard />;
    }

    // Default View for Officers/Sales Assistants
    return (
        <div className="text-center mt-20">
            <h1 className="text-4xl font-bold text-slate-800 mb-4">Welcome back, {user?.user?.username}</h1>
            <p className="text-gray-600 text-lg">Use the sidebar to navigate to Inventory or Sales.</p>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto mt-12">
                {/* Fixed: Changed div to Link */}
                <Link to="/sales/new" className="bg-white p-6 rounded shadow hover:shadow-lg transition cursor-pointer block border border-transparent hover:border-blue-500">
                    <h3 className="text-xl font-bold text-blue-600">Quick Invoice</h3>
                    <p className="text-gray-500 mt-2">Create a new sales invoice immediately.</p>
                </Link>

                {/* Fixed: Changed div to Link */}
                <Link to="/inventory" className="bg-white p-6 rounded shadow hover:shadow-lg transition cursor-pointer block border border-transparent hover:border-green-500">
                    <h3 className="text-xl font-bold text-green-600">Check Stock</h3>
                    <p className="text-gray-500 mt-2">View current inventory levels.</p>
                </Link>
            </div>
        </div>
    );
};

export default Dashboard;
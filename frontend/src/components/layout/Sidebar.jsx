import { Link, useLocation } from "react-router-dom";
import { useContext } from "react";
import { AuthContext } from "../../context/AuthContext";
import {
    FaTachometerAlt, FaFileInvoiceDollar, FaWarehouse, FaIndustry,
    FaUsers, FaCreditCard, FaCalculator, FaChartBar,
    FaUserCog, FaBoxOpen, FaBuilding, FaSignOutAlt
} from "react-icons/fa";
import { ROLES } from "../../utils/roleConstants";

const Sidebar = () => {
    const { user } = useContext(AuthContext);
    const location = useLocation();

    // Helper to check active link for styling
    const isActive = (path) => location.pathname === path ? "bg-white/20 border-r-4 border-white" : "hover:bg-white/10";

    return (
        <div className="w-64 bg-indigo-700 text-white h-screen fixed flex flex-col shadow-xl font-[Inter]">
            {/* Logo Area */}
            <div className="p-6 border-b border-indigo-600 flex items-center gap-3">
                <FaBoxOpen className="text-3xl text-white" />
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">TMS</h1>
                    <p className="text-xs text-indigo-200">Textile Management</p>
                </div>
            </div>

            {/* Navigation Items */}
            <nav className="flex-1 overflow-y-auto py-4 space-y-1">
                <SidebarItem to="/" icon={<FaTachometerAlt />} label="Dashboard" active={isActive('/')} />

                <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Operations</p>
                <SidebarItem to="/sales" icon={<FaFileInvoiceDollar />} label="Sales & Invoices" active={isActive('/sales')} />
                <SidebarItem to="/inventory" icon={<FaWarehouse />} label="Inventory" active={isActive('/inventory')} />
                <SidebarItem to="/production" icon={<FaIndustry />} label="Production" active={isActive('/production')} />

                <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Finance & CRM</p>
                <SidebarItem to="/customers" icon={<FaUsers />} label="Customers" active={isActive('/customers')} />
                <SidebarItem to="/sales/credit" icon={<FaCreditCard />} label="Credit Sales" active={isActive('/sales/credit')} />
                <SidebarItem to="/finance" icon={<FaCalculator />} label="Accounting" active={isActive('/finance')} />

                <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Administration</p>
                <SidebarItem to="/reports" icon={<FaChartBar />} label="Reports" active={isActive('/reports')} />
                {/* Only show User Mgmt to Admins */}
                {(user?.user?.role === 'Administrator' || user?.user?.role === ROLES.ADMIN) && (
                    <SidebarItem to="/admin/users" icon={<FaUserCog />} label="User Management" active={isActive('/admin/users')} />
                )}
                <SidebarItem to="/products" icon={<FaBoxOpen />} label="Products" active={isActive('/products')} />
                <SidebarItem to="/branches" icon={<FaBuilding />} label="Branches" active={isActive('/branches')} />
            </nav>
        </div>
    );
};

// Reusable Menu Item Component
const SidebarItem = ({ to, icon, label, active }) => (
    <Link to={to} className={`flex items-center px-6 py-3 transition-colors duration-200 ${active}`}>
        <span className="text-lg mr-3">{icon}</span>
        <span className="text-sm font-medium">{label}</span>
    </Link>
);

export default Sidebar;
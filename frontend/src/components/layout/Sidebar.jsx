import { Link, useLocation } from "react-router-dom";
import { useContext } from "react";
import { AuthContext } from "../../context/AuthContext";
import { ROLES } from "../../utils/roleConstants";
import { 
    FaTachometerAlt, FaFileInvoiceDollar, FaWarehouse, FaIndustry, 
    FaUsers, FaCreditCard, FaCalculator, FaChartBar, 
    FaUserCog, FaBoxOpen, FaBuilding, FaSignOutAlt, FaTimes 
} from "react-icons/fa";

const Sidebar = ({ isOpen, closeSidebar }) => {
    const { user, logout } = useContext(AuthContext);
    const location = useLocation();
    const role = user?.user?.role;
    
    const isActive = (path) => location.pathname === path ? "bg-white/20 border-r-4 border-white" : "hover:bg-white/10";

    return (
        <>
            {/* Sidebar Container */}
            <div className={`
                fixed inset-y-0 left-0 z-30 w-64 bg-indigo-700 text-white shadow-xl 
                transform transition-transform duration-300 ease-in-out font-[Inter]
                ${isOpen ? "translate-x-0" : "-translate-x-full"} 
                md:translate-x-0 md:fixed
            `}>
                {/* Logo Area */}
                <div className="p-6 border-b border-indigo-600 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <FaBoxOpen className="text-3xl text-white" />
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">TMS</h1>
                            <p className="text-xs text-indigo-200">Textile Management</p>
                        </div>
                    </div>
                    {/* Close Button (Mobile Only) */}
                    <button onClick={closeSidebar} className="md:hidden text-indigo-200 hover:text-white">
                        <FaTimes size={24} />
                    </button>
                </div>
                
                {/* Navigation Items */}
                <nav className="flex-1 overflow-y-auto py-4 space-y-1">
                    <SidebarItem to="/" icon={<FaTachometerAlt />} label="Dashboard" active={isActive('/')} onClick={closeSidebar} />
                    
                    <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Operations</p>
                    <SidebarItem to="/sales/new" icon={<FaFileInvoiceDollar />} label="Sales & Invoices" active={isActive('/sales/new')} onClick={closeSidebar} />
                    <SidebarItem to="/inventory" icon={<FaWarehouse />} label="Inventory" active={isActive('/inventory')} onClick={closeSidebar} />
                    <SidebarItem to="/production" icon={<FaIndustry />} label="Production" active={isActive('/production')} onClick={closeSidebar} />
                    
                    <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Finance & CRM</p>
                    <SidebarItem to="/customers" icon={<FaUsers />} label="Customers" active={isActive('/customers')} onClick={closeSidebar} />
                    <SidebarItem to="/sales/credit" icon={<FaCreditCard />} label="Credit Sales" active={isActive('/sales/credit')} onClick={closeSidebar} />
                    <SidebarItem to="/finance" icon={<FaCalculator />} label="Accounting" active={isActive('/finance')} onClick={closeSidebar} />
                    
                    <p className="px-6 py-2 text-xs font-semibold text-indigo-300 uppercase mt-2">Administration</p>
                    <SidebarItem to="/reports" icon={<FaChartBar />} label="Reports" active={isActive('/reports')} onClick={closeSidebar} />
                    
                    {/* Admin Only Links */}
                    {(role === 'Administrator' || role === ROLES.ADMIN) && (
                        <SidebarItem to="/admin/users" icon={<FaUserCog />} label="User Management" active={isActive('/admin/users')} onClick={closeSidebar} />
                    )}
                    
                    <SidebarItem to="/inventory/products" icon={<FaBoxOpen />} label="Products" active={isActive('/inventory/products')} onClick={closeSidebar} />
                    <SidebarItem to="/branches" icon={<FaBuilding />} label="Branches" active={isActive('/branches')} onClick={closeSidebar} />
                </nav>

                {/* Footer (Logout) */}
                <div className="p-4 bg-indigo-800 border-t border-indigo-600">
                    <button onClick={logout} className="flex items-center gap-2 text-red-200 hover:text-white text-sm w-full transition">
                        <FaSignOutAlt /> Logout
                    </button>
                </div>
            </div>
        </>
    );
};

// Helper Component for Menu Items
const SidebarItem = ({ to, icon, label, active, onClick }) => (
    <Link to={to} onClick={onClick} className={`flex items-center px-6 py-3 transition-colors duration-200 ${active}`}>
        <span className="text-lg mr-3">{icon}</span>
        <span className="text-sm font-medium">{label}</span>
    </Link>
);

export default Sidebar;

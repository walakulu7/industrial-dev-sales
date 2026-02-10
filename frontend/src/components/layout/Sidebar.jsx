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
    const userRole = user?.user?.role;
    
    // Check active state
    const isActive = (path) => {
        if (path === '/') return location.pathname === '/';
        return location.pathname.startsWith(path);
    };

    // Helper: Check permission
    const hasAccess = (allowedRoles) => allowedRoles.includes(userRole);

    return (
        <>
            {/* Sidebar Container */}
            <div className={`
                fixed inset-y-0 left-0 z-30 w-72 bg-[#111827] text-gray-400 shadow-2xl 
                transform transition-transform duration-300 ease-in-out font-[Inter] flex flex-col
                ${isOpen ? "translate-x-0" : "-translate-x-full"} 
                md:translate-x-0 md:fixed border-r border-gray-800
            `}>
                
                {/* 1. Brand / Header */}
                <div className="h-16 flex items-center px-6 border-b border-gray-800 bg-[#0f1523]">
                    <div className="flex items-center gap-3">
                        <div className="bg-blue-600 p-1.5 rounded-md text-white">
                            <FaBoxOpen className="text-lg" />
                        </div>
                        <div>
                            <h1 className="text-lg font-bold text-white tracking-wide leading-none">TMS</h1>
                            <p className="text-[10px] uppercase text-blue-500 font-bold tracking-widest mt-0.5">Enterprise</p>
                        </div>
                    </div>
                    <button onClick={closeSidebar} className="md:hidden ml-auto text-gray-400 hover:text-white">
                        <FaTimes size={20} />
                    </button>
                </div>
                
                {/* 2. Navigation Items (Scrollable with custom scrollbar) */}
                <nav className="flex-1 overflow-y-auto py-6 px-3 space-y-1 custom-scrollbar">
                    
                    <SidebarItem to="/" icon={<FaTachometerAlt />} label="Dashboard" active={isActive('/')} onClick={closeSidebar} />
                    
                    {/* OPERATIONS */}
                    {(hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.OFFICER, ROLES.SALES_ASSISTANT])) && (
                        <SectionLabel label="Operations" />
                    )}

                    <SidebarItem to="/sales/new" icon={<FaFileInvoiceDollar />} label="New Invoice" active={isActive('/sales/new')} onClick={closeSidebar} />
                    <SidebarItem to="/sales" icon={<FaFileInvoiceDollar />} label="Sales History" active={isActive('/sales') && !isActive('/sales/new')} onClick={closeSidebar} />

                    {hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.OFFICER]) && (
                        <>
                            <SidebarItem to="/inventory" icon={<FaWarehouse />} label="Inventory Stock" active={isActive('/inventory') && !isActive('/inventory/products') && !isActive('/inventory/items')} onClick={closeSidebar} />
                            <SidebarItem to="/production" icon={<FaIndustry />} label="Production" active={isActive('/production')} onClick={closeSidebar} />
                        </>
                    )}
                    
                    {/* FINANCE */}
                    {(hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.ACCOUNTANT, ROLES.OFFICER])) && (
                        <SectionLabel label="Finance & CRM" />
                    )}

                    <SidebarItem to="/customers" icon={<FaUsers />} label="Customers" active={isActive('/customers')} onClick={closeSidebar} />

                    {hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.ACCOUNTANT]) && (
                        <>
                            <SidebarItem to="/sales/credit" icon={<FaCreditCard />} label="Credit Sales" active={isActive('/sales/credit')} onClick={closeSidebar} />
                            <SidebarItem to="/finance" icon={<FaCalculator />} label="Accounting" active={isActive('/finance')} onClick={closeSidebar} />
                        </>
                    )}
                    
                    {/* ADMIN */}
                    {hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.ACCOUNTANT]) && (
                        <SectionLabel label="Administration" />
                    )}

                    {hasAccess([ROLES.ADMIN, ROLES.DIRECTOR, ROLES.ACCOUNTANT]) && (
                        <SidebarItem to="/reports" icon={<FaChartBar />} label="Reports" active={isActive('/reports')} onClick={closeSidebar} />
                    )}
                    
                    {hasAccess([ROLES.ADMIN]) && (
                        <SidebarItem to="/admin/users" icon={<FaUserCog />} label="User Management" active={isActive('/admin/users')} onClick={closeSidebar} />
                    )}
                    
                    {hasAccess([ROLES.ADMIN, ROLES.DIRECTOR]) && (
                        <>
                            <SidebarItem to="/inventory/products" icon={<FaBoxOpen />} label="Finished Goods" active={isActive('/inventory/products')} onClick={closeSidebar} />
                            <SidebarItem to="/inventory/items" icon={<FaBoxOpen />} label="Product Master" active={isActive('/inventory/items')} onClick={closeSidebar} />
                            <SidebarItem to="/branches" icon={<FaBuilding />} label="Branches" active={isActive('/branches')} onClick={closeSidebar} />
                        </>
                    )}
                </nav>

                {/* 3. Footer */}
                <div className="p-4 border-t border-gray-800 bg-[#0f1523]">
                    <button 
                        onClick={logout} 
                        className="flex items-center justify-center w-full gap-2 bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white py-2.5 rounded-lg transition-all duration-200 font-medium text-sm group"
                    >
                        <FaSignOutAlt className="group-hover:-translate-x-1 transition-transform" /> 
                        <span>Sign Out</span>
                    </button>
                </div>
            </div>

            {/* Custom Scrollbar Styles (Injected locally) */}
            <style>{`
                .custom-scrollbar::-webkit-scrollbar {
                    width: 5px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background-color: #374151;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background-color: #4b5563;
                }
            `}</style>
        </>
    );
};

// --- Updated Sub Components ---

const SectionLabel = ({ label }) => (
    <div className="px-4 pt-6 pb-2">
        <p className="text-[10px] uppercase font-bold text-gray-500 tracking-wider">{label}</p>
    </div>
);

const SidebarItem = ({ to, icon, label, active, onClick }) => (
    <Link 
        to={to} 
        onClick={onClick} 
        className={`
            flex items-center px-4 py-3 mx-2 rounded-lg transition-all duration-200 group relative
            ${active 
                ? "bg-blue-600/10 text-blue-400" 
                : "text-gray-400 hover:bg-gray-800 hover:text-gray-100"
            }
        `}
    >
        {/* Active Indicator Bar */}
        {active && (
            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-blue-500 rounded-r-md"></div>
        )}

        <span className={`text-lg mr-3 ${active ? "text-blue-400" : "text-gray-500 group-hover:text-gray-300 transition-colors"}`}>
            {icon}
        </span>
        <span className={`text-sm font-medium ${active ? "font-bold" : ""}`}>{label}</span>
    </Link>
);

export default Sidebar;

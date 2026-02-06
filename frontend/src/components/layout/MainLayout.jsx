import { useState } from "react";
import Sidebar from "./Sidebar";
import Navbar from "./Navbar";
import { Outlet } from "react-router-dom";

const MainLayout = () => {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (
        <div className="flex h-screen bg-slate-50 overflow-hidden">
            {/* Sidebar (Mobile: Toggled, Desktop: Fixed) */}
            <Sidebar isOpen={isSidebarOpen} closeSidebar={() => setIsSidebarOpen(false)} />

            {/* Overlay for Mobile (closes sidebar when clicked) */}
            {isSidebarOpen && (
                <div 
                    className="fixed inset-0 z-20 bg-black/50 md:hidden"
                    onClick={() => setIsSidebarOpen(false)}
                ></div>
            )}

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col md:ml-64 transition-all duration-300">
                {/* Navbar (Pass toggle function) */}
                <Navbar toggleSidebar={() => setIsSidebarOpen(!isSidebarOpen)} />

                {/* Page Content (Scrollable) */}
                <div className="flex-1 overflow-auto p-4 md:p-8">
                    <Outlet /> 
                </div>
            </div>
        </div>
    );
};

export default MainLayout;

import { useState } from "react";
import Sidebar from "./Sidebar";
import Navbar from "./Navbar";
import { Outlet } from "react-router-dom";

const MainLayout = () => {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (
        <div className="flex h-screen bg-slate-50 overflow-hidden font-[Inter]">
            
            {/* 1. Sidebar (Fixed Width: w-72) */}
            <Sidebar 
                isOpen={isSidebarOpen} 
                closeSidebar={() => setIsSidebarOpen(false)} 
            />

            {/* 2. Mobile Overlay (Darkens background when menu is open on phone) */}
            {isSidebarOpen && (
                <div 
                    className="fixed inset-0 z-20 bg-black/50 md:hidden backdrop-blur-sm transition-opacity"
                    onClick={() => setIsSidebarOpen(false)}
                ></div>
            )}

            {/* 3. Main Content Wrapper */}
            {/* md:ml-72 matches the Sidebar's w-72 exactly */}
            <div className="flex-1 flex flex-col md:ml-72 transition-all duration-300 h-full relative">
                
                {/* Navbar (Sticky Top) */}
                <Navbar toggleSidebar={() => setIsSidebarOpen(!isSidebarOpen)} />

                {/* Page Content (Scrollable Area) */}
                <main className="flex-1 overflow-auto p-4 md:p-8 bg-slate-50">
                    <Outlet /> 
                </main>
            </div>
        </div>
    );
};

export default MainLayout;

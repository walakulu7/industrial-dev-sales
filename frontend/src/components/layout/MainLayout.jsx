import Sidebar from "./Sidebar";
import Navbar from "./Navbar"; // Import Navbar
import { Outlet } from "react-router-dom";

const MainLayout = () => {
    return (
        <div className="flex h-screen bg-slate-50 overflow-hidden">
            {/* Sidebar stays fixed on the left */}
            <Sidebar />

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col ml-64">
                {/* Navbar on top */}
                <Navbar />

                {/* Page Content (Scrollable) */}
                <div className="flex-1 overflow-auto p-8">
                    <Outlet />
                </div>
            </div>
        </div>
    );
};

export default MainLayout;
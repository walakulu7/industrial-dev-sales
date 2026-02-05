import { useState, useContext, useRef, useEffect } from "react";
import { Link } from "react-router-dom";
import { AuthContext } from "../../context/AuthContext";
import { FaUserCircle, FaCaretDown, FaUser, FaKey, FaSignOutAlt } from "react-icons/fa";
import API from "../../api/axiosConfig";

const Navbar = () => {
    const { user, logout } = useContext(AuthContext);
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [showPwdModal, setShowPwdModal] = useState(false);
    const [passwords, setPasswords] = useState({ current: "", new: "" });
    const dropdownRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setDropdownOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const handleChangePassword = async (e) => {
        e.preventDefault();
        try {
            await API.put('/auth/change-password', { 
                currentPassword: passwords.current, 
                newPassword: passwords.new 
            });
            alert("Password changed successfully");
            setShowPwdModal(false);
            setPasswords({ current: "", new: "" });
        } catch (err) {
            alert(err.response?.data?.message || "Failed to change password");
        }
    };

    return (
        <div className="bg-white h-16 shadow-sm flex justify-between items-center px-6 relative z-20">
            {/* Left Side: Branch Name */}
            <div className="flex items-center text-gray-500">
                <span className="font-medium">{user?.user?.branch || "Textile ERP System"}</span>
            </div>

            {/* Right Side: User Dropdown */}
            <div className="relative" ref={dropdownRef}>
                <button 
                    onClick={() => setDropdownOpen(!dropdownOpen)}
                    className="flex items-center gap-3 hover:bg-gray-50 p-2 rounded transition focus:outline-none"
                >
                    <div className="text-right hidden md:block">
                        <p className="text-sm font-bold text-slate-700">{user?.user?.name || "User"}</p>
                        <p className="text-xs text-blue-600 font-semibold">{user?.user?.role}</p>
                    </div>
                    <FaUserCircle className="text-3xl text-gray-400" />
                    <FaCaretDown className="text-gray-400 text-sm" />
                </button>

                {/* Dropdown Menu */}
                {dropdownOpen && (
                    <div className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-100 py-2 animate-fade-in-up">
                        {/* --- FIXED SECTION START --- */}
                        <div className="px-4 py-3 border-b border-gray-100">
                            {/* Changed 'Administrator' to dynamic Role */}
                            <p className="text-sm font-bold text-gray-800">{user?.user?.role}</p>
                            <p className="text-xs text-gray-500 truncate">{user?.user?.username}</p>
                        </div>
                        {/* --- FIXED SECTION END --- */}

                        <Link 
                            to="/profile" 
                            onClick={() => setDropdownOpen(false)}
                            className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <FaUser className="mr-3" /> Profile
                        </Link>

                        <button 
                            onClick={() => { setDropdownOpen(false); setShowPwdModal(true); }}
                            className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <FaKey className="mr-3" /> Change Password
                        </button>

                        <div className="border-t border-gray-100 my-1"></div>

                        <button 
                            onClick={logout}
                            className="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                        >
                            <FaSignOutAlt className="mr-3" /> Logout
                        </button>
                    </div>
                )}
            </div>

            {/* Change Password Modal */}
            {showPwdModal && (
                <div className="fixed inset-0 bg-black/50 flex justify-center items-center z-50">
                    <div className="bg-white p-6 rounded shadow-xl w-96">
                        <h3 className="text-lg font-bold mb-4">Change Password</h3>
                        <form onSubmit={handleChangePassword}>
                            <input type="password" placeholder="Current Password" className="w-full border p-2 rounded mb-3" required
                                onChange={e => setPasswords({...passwords, current: e.target.value})} />
                            <input type="password" placeholder="New Password" className="w-full border p-2 rounded mb-4" required
                                onChange={e => setPasswords({...passwords, new: e.target.value})} />
                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setShowPwdModal(false)} className="text-gray-500 px-3">Cancel</button>
                                <button type="submit" className="bg-indigo-600 text-white px-4 py-2 rounded">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Navbar;
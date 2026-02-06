import { useState, useContext } from "react";
import API from "../../api/axiosConfig";
import { AuthContext } from "../../context/AuthContext";
import { useNavigate } from "react-router-dom";
import { FaUser, FaLock, FaSpinner, FaIndustry } from "react-icons/fa";

const Login = () => {
    const [formData, setFormData] = useState({ username: "", password: "" });
    const [loading, setLoading] = useState(false); // Added loading state for better UX
    const [error, setError] = useState("");
    
    const { login } = useContext(AuthContext);
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError("");

        try {
            const res = await API.post("/auth/login", formData);
            login(res.data); // Save token & User data
            navigate("/");   // Redirect to Dashboard
        } catch (err) {
            setError(err.response?.data?.message || "Invalid credentials. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-gray-100 flex items-center justify-center p-4">
            
            {/* Main Card Container */}
            <div className="max-w-4xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row animate-fade-in-up">
                
                {/* LEFT SIDE: Branding (Hidden on Mobile) */}
                <div className="w-full md:w-1/2 bg-indigo-900 p-12 text-white hidden md:flex flex-col justify-center items-center relative">
                    {/* Background Pattern/Gradient */}
                    <div className="absolute inset-0 bg-gradient-to-br from-indigo-600 to-blue-900 opacity-90"></div>
                    
                    {/* Branding Content */}
                    <div className="relative z-10 text-center">
                        <div className="bg-white/20 p-4 rounded-full inline-block mb-6 backdrop-blur-sm">
                            <FaIndustry className="text-6xl text-white" />
                        </div>
                        <h1 className="text-4xl font-bold mb-2 tracking-tight">Textile ERP</h1>
                        <p className="text-indigo-200 text-lg">Management System</p>
                        
                        <div className="mt-10 space-y-3 text-sm font-medium text-indigo-100/80">
                            <p>✓ Inventory Tracking</p>
                            <p>✓ Production Planning</p>
                            <p>✓ Sales & Finance</p>
                        </div>
                    </div>
                </div>

                {/* RIGHT SIDE: Login Form */}
                <div className="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center">
                    
                    {/* Mobile Logo Header */}
                    <div className="text-center md:hidden mb-8">
                        <div className="bg-indigo-600 text-white p-3 rounded-full inline-block mb-3">
                            <FaIndustry className="text-3xl" />
                        </div>
                        <h1 className="text-2xl font-bold text-gray-800">Textile ERP</h1>
                    </div>

                    <div className="text-center md:text-left mb-8">
                        <h2 className="text-2xl font-bold text-gray-800">Welcome Back</h2>
                        <p className="text-gray-500 mt-1">Please enter your details to sign in.</p>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="bg-red-50 border-l-4 border-red-500 text-red-600 p-3 mb-6 text-sm rounded shadow-sm">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        
                        {/* Username Input */}
                        <div>
                            <label className="text-sm font-semibold text-gray-700 block mb-2">Username</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <FaUser className="text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                                    placeholder="Enter your username"
                                    value={formData.username}
                                    onChange={(e) => setFormData({...formData, username: e.target.value})}
                                    required
                                />
                            </div>
                        </div>

                        {/* Password Input */}
                        <div>
                            <label className="text-sm font-semibold text-gray-700 block mb-2">Password</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <FaLock className="text-gray-400" />
                                </div>
                                <input
                                    type="password"
                                    className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                                    placeholder="Enter your password"
                                    value={formData.password}
                                    onChange={(e) => setFormData({...formData, password: e.target.value})}
                                    required
                                />
                            </div>
                        </div>

                        {/* Additional Options */}
                        <div className="flex items-center justify-between text-sm">
                            <label className="flex items-center text-gray-600 cursor-pointer select-none">
                                <input type="checkbox" className="mr-2 rounded text-indigo-600 focus:ring-indigo-500" />
                                Remember me
                            </label>
                            <a href="#" className="text-indigo-600 hover:text-indigo-700 font-semibold hover:underline">Forgot password?</a>
                        </div>

                        {/* Login Button */}
                        <button
                            type="submit"
                            disabled={loading}
                            className={`w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition duration-200 flex justify-center items-center shadow-lg transform active:scale-95 ${loading ? 'opacity-70 cursor-not-allowed' : ''}`}
                        >
                            {loading ? (
                                <>
                                    <FaSpinner className="animate-spin mr-2" /> Signing In...
                                </>
                            ) : (
                                "Sign In"
                            )}
                        </button>
                    </form>

                    <div className="mt-8 text-center text-xs text-gray-400">
                        &copy; 2026 Viskampiyasa (Pvt) Ltd. All rights reserved.
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Login;

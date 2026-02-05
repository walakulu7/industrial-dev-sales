import { useNavigate } from "react-router-dom";
import { FaLock } from "react-icons/fa";

const Unauthorized = () => {
    const navigate = useNavigate();

    return (
        <div className="flex flex-col items-center justify-center h-screen bg-gray-100 text-center px-4">
            <div className="bg-white p-10 rounded-lg shadow-lg max-w-md">
                <div className="flex justify-center mb-6">
                    <div className="p-4 bg-red-100 rounded-full">
                        <FaLock className="text-4xl text-red-500" />
                    </div>
                </div>
                <h1 className="text-3xl font-bold text-slate-800 mb-2">Access Denied</h1>
                <p className="text-gray-600 mb-8">
                    You do not have the necessary permissions to view this page. Please contact your administrator if you believe this is an error.
                </p>
                <div className="flex gap-4 justify-center">
                    <button 
                        onClick={() => navigate(-1)} 
                        className="px-6 py-2 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Go Back
                    </button>
                    <button 
                        onClick={() => navigate('/')} 
                        className="px-6 py-2 bg-slate-900 text-white rounded hover:bg-slate-800 transition"
                    >
                        Dashboard
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Unauthorized;
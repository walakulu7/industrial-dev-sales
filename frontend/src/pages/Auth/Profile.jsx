import { useContext } from "react";
import { AuthContext } from "../../context/AuthContext";
import { FaUserCircle, FaBuilding, FaIdBadge, FaEnvelope } from "react-icons/fa";

const Profile = () => {
    const { user } = useContext(AuthContext);
    const userInfo = user?.user;

    return (
        <div className="max-w-4xl mx-auto animate-fade-in-up">
            <h1 className="text-2xl font-bold text-slate-800 mb-6">My Profile</h1>

            <div className="bg-white rounded-lg shadow-md overflow-hidden flex flex-col md:flex-row">
                {/* Left Side: Avatar & Role */}
                <div className="bg-indigo-600 p-8 flex flex-col items-center justify-center text-white md:w-1/3">
                    <FaUserCircle className="text-9xl mb-4 opacity-80" />
                    <h2 className="text-2xl font-bold">{userInfo?.name}</h2>
                    <span className="bg-indigo-800 px-3 py-1 rounded-full text-sm mt-2">
                        {userInfo?.role}
                    </span>
                </div>

                {/* Right Side: Details */}
                <div className="p-8 md:w-2/3">
                    <h3 className="text-xl font-bold text-gray-700 mb-6 border-b pb-2">Account Details</h3>

                    <div className="grid grid-cols-1 gap-6">
                        <div className="flex items-center">
                            <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4">
                                <FaIdBadge />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Username</p>
                                <p className="font-medium text-gray-800">{userInfo?.username}</p>
                            </div>
                        </div>

                        <div className="flex items-center">
                            <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-4">
                                <FaBuilding />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Branch / Location</p>
                                <p className="font-medium text-gray-800">{userInfo?.branch}</p>
                            </div>
                        </div>

                        <div className="flex items-center">
                            <div className="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 mr-4">
                                <FaEnvelope />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">System Status</p>
                                <p className="font-medium text-green-600">Active</p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 p-4 bg-gray-50 rounded border border-gray-100 text-sm text-gray-500">
                        <p>To change your personal details, please contact the System Administrator.</p>
                        <p>You can change your password using the top-right menu.</p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Profile;
import { createContext, useState, useContext } from "react";

const GlobalContext = createContext();

export const GlobalProvider = ({ children }) => {
    const [isLoading, setIsLoading] = useState(false);
    const [alert, setAlert] = useState({ show: false, message: '', type: 'info' }); 

    const showAlert = (message, type = 'info') => {
        setAlert({ show: true, message, type });
        // Auto-hide after 3 seconds
        setTimeout(() => setAlert({ show: false, message: '', type: 'info' }), 3000);
    };

    return (
        <GlobalContext.Provider value={{ isLoading, setIsLoading, alert, showAlert }}>
            {children}
            
            {/* Global Alert Toast */}
            {alert.show && (
                <div className={`fixed top-5 right-5 z-50 px-6 py-3 rounded shadow-lg text-white font-medium transition-all transform duration-300
                    ${alert.type === 'error' ? 'bg-red-500' : alert.type === 'success' ? 'bg-green-600' : 'bg-blue-600'}`}>
                    {alert.message}
                </div>
            )}
            
            {/* Global Loading Overlay */}
            {isLoading && (
                <div className="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 backdrop-blur-sm">
                    <div className="flex flex-col items-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-t-4 border-b-4 border-white mb-3"></div>
                        <span className="text-white font-semibold">Processing...</span>
                    </div>
                </div>
            )}
        </GlobalContext.Provider>
    );
};

export const useGlobal = () => useContext(GlobalContext);
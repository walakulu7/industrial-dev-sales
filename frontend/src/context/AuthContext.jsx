import { createContext, useState, useEffect } from "react";
import PropTypes from "prop-types"; // 1. Fix: Import PropTypes

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // Check for stored token on app load
        const storedUser = localStorage.getItem("user_data");
        if (storedUser) {
            try {
                const parsedUser = JSON.parse(storedUser);
                setUser(parsedUser);
            } catch (error) {
                console.error("Failed to parse user data", error);
                localStorage.removeItem("user_data");
            }
        }
        setLoading(false);
    }, []);

    const login = (userData) => {
        if (userData) {
            localStorage.setItem("user_data", JSON.stringify(userData));
            setUser(userData);
        }
    };

    const logout = () => {
        localStorage.removeItem("user_data");
        setUser(null);
        // Using window.location.href ensures a full state reset
        window.location.href = "/login"; 
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, loading }}>
            {children}
        </AuthContext.Provider>
    );
};

// 2. Fix: Add PropTypes validation to satisfy linter
AuthProvider.propTypes = {
    children: PropTypes.node.isRequired,
};
import { useState, useEffect, useCallback } from "react";
import API from "../api/axiosConfig";

const useFetch = (url, dependencies = []) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const response = await API.get(url);
            setData(response.data);
            setError(null);
        } catch (err) {
            console.error(`Error fetching ${url}:`, err);
            setError(err.response ? err.response.data : "Network Error");
        } finally {
            setLoading(false);
        }
    }, [url]);

    useEffect(() => {
        fetchData();
    }, [fetchData, ...dependencies]);

    return { data, loading, error, refetch: fetchData };
};

export default useFetch;
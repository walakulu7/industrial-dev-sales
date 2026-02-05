exports.calculateDepreciationValue = (originalValue, rate, yearsPassed) => {
    // Straight Line Method: (Cost * Rate) * Years
    // Reducing Balance Method can be implemented here if needed
    const depreciationAmount = originalValue * (rate / 100) * yearsPassed;
    const currentValue = originalValue - depreciationAmount;
    return currentValue > 0 ? currentValue : 0;
};

exports.calculateTax = (amount, taxRate) => {
    return amount * (taxRate / 100);
};

exports.formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-LK', { style: 'currency', currency: 'LKR' }).format(amount);
};
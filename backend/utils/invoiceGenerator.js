exports.generateInvoiceNumber = (location, sequenceId) => {
    // Map full location names to short codes
    const locationCodes = {
        'Viskampiyasa': 'VIS',
        'Viskampiyasa_Main_Office': 'VIS',
        'Polonnaruwa': 'POL',
        'Polonnaruwa_Sub_Office': 'POL',
        'Anuradhapura': 'ANU'
    };

    const code = locationCodes[location] || 'GEN'; // GEN = General
    const year = new Date().getFullYear();
    
    // Format: VIS-2024-0005
    // Ensure sequenceId is padded with zeros (e.g., 5 -> 0005)
    const paddedId = String(sequenceId).padStart(4, '0');
    
    return `${code}-${year}-${paddedId}`;
};
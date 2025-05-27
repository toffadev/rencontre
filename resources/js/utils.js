/**
 * Formate une durée en secondes en format lisible
 * @param {number} seconds - Nombre de secondes à formater
 * @returns {string} Durée formatée (ex: "2h 30m" ou "45s")
 */
export const formatDuration = (seconds) => {
    if (!seconds) return '0s';

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    const parts = [];
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);
    if (remainingSeconds > 0 && hours === 0) parts.push(`${remainingSeconds}s`);

    return parts.join(' ');
};

/**
 * Formate un montant en euros
 * @param {number} amount - Montant à formater
 * @returns {string} Montant formaté (ex: "123,45 €")
 */
export const formatCurrency = (amount) => {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
};

export function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
} 
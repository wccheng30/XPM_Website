// Load Featured Programmes
async function loadFeaturedProgrammes() {
    const container = document.getElementById('featured-programmes-container');
    if (!container) return;

    try {
        const response = await fetch('api/featured-programmes.php');
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            let html = '<div class="featured-programmes-wrapper" style="display: grid; gap: 20px; margin-bottom: 40px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">';
            
            result.data.forEach(programme => {
                html += `
                    <div class="programme-card featured-card" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); position: relative; overflow: hidden; transition: transform 0.3s ease;">
                        <div style="position: absolute; top: 0; right: 0; background: #f59e0b; color: white; padding: 4px 12px; font-size: 12px; font-weight: 600; border-bottom-left-radius: 12px;">
                            <i class="fas fa-star" style="margin-right: 4px;"></i> Featured
                        </div>
                        <h3 style="font-size: 20px; color: #1e293b; margin-top: 10px; margin-bottom: 12px;">${programme.title}</h3>
                        <p style="color: #64748b; font-size: 14px; margin-bottom: 16px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">${programme.description}</p>
                        
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
                            <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                <i class="fas fa-clock" style="margin-right: 4px;"></i> ${programme.duration}
                            </span>
                            <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                <i class="fas fa-tag" style="margin-right: 4px;"></i> ${programme.category}
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                            <div>
                                <span style="font-size: 12px; color: #64748b; display: block; margin-bottom: 2px;">Investment</span>
                                <span style="font-size: 18px; font-weight: 700; color: #3b82f6;">${programme.formatted_price}</span>
                            </div>
                            <a href="programme-registration.php?id=${programme.id}" class="btn btn-primary btn-sm" style="padding: 8px 16px; font-size: 14px;">
                                Register Now <i class="fas fa-arrow-right" style="margin-left: 6px;"></i>
                            </a>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading featured programmes:', error);
    }
}

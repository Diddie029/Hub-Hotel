// ==========================================
// Landing Page JavaScript
// Smart Restaurant System
// ==========================================

const chatbot = {
    isOpen: false,
    isInitialized: false,

    // Knowledge base for chatbot responses
    responses: {
        wifi: {
            keywords: ['wifi', 'password', 'internet', 'network', 'connect'],
            responses: [
                '📶 WiFi Password: SmartRestaurant2024\nNetwork: SmartRestaurant_Guest\nSpeed: 100 Mbps',
                '🔐 Our WiFi: SmartRestaurant_Guest\n🔑 Password: SmartRestaurant2024'
            ]
        },
        hours: {
            keywords: ['hours', 'open', 'close', 'timing', 'when'],
            responses: [
                '⏰ Restaurant Hours:\n📅 Mon-Thu: 10:00 AM - 11:00 PM\n📅 Fri-Sat: 10:00 AM - 1:00 AM\n📅 Sunday: 12:00 PM - 10:00 PM',
                '⏳ Opening hours: 10 AM - 11 PM | Extended hours Fri-Sat until 1 AM'
            ]
        },
        phone: {
            keywords: ['phone', 'contact', 'call', 'number', 'reach'],
            responses: [
                '📞 Call us at: +254 700 123 456\n📧 Email: info@smartrestaurant.com',
                'Contact us: 📱 +254 700 123 456 | 📧 info@smartrestaurant.com'
            ]
        },
        delivery: {
            keywords: ['delivery', 'robot', 'how long', 'time', 'arrive'],
            responses: [
                '🤖 Our robots deliver in 15-20 minutes average!\n⚡ Real-time tracking available\n✨ Contactless delivery',
                '⏱️ Average delivery time: 15-20 minutes with live robot tracking 🤖'
            ]
        },
        payment: {
            keywords: ['payment', 'pay', 'mpesa', 'cash', 'card', 'accept'],
            responses: [
                '💳 Payment Options:\n📱 M-Pesa (Most Popular)\n💰 Cash on Delivery\n🏦 Bank Transfer\n💳 Debit/Credit Card',
                'We accept: M-Pesa 📱 | Cash 💰 | Bank Transfer 🏦 | Cards 💳'
            ]
        },
        menu: {
            keywords: ['menu', 'food', 'eat', 'dish', 'recommend', 'vegetarian', 'vegan'],
            responses: [
                '🍽️ Check our full menu in the app!\n⭐ Popular: Nyama Choma, Ugali, Grilled Fish\n🌱 Vegetarian options available',
                'Browse our menu to see all options! Popular dishes: Nyama Choma, Fish, Ugali 🍽️'
            ]
        },
        promo: {
            keywords: ['promo', 'discount', 'offer', 'deal', 'special', 'coupon'],
            responses: [
                '🎉 Current Offers:\n50% off first order (NEW users)\n🤖 Free delivery on orders >KES 500\n👥 Referral bonus: KES 200 per friend',
                '✨ Special offer: 50% off first order!\n🚚 Free delivery on orders above KES 500'
            ]
        },
        location: {
            keywords: ['location', 'address', 'where', 'find us'],
            responses: [
                '📍 Location: 123 Restaurant Avenue, Nairobi\n🗺️ Near Westgate Mall\n🅿️ Parking available',
                'Find us at: 123 Restaurant Avenue, Nairobi - Easy parking available! 🅿️'
            ]
        }
    },

    /**
     * Initialize chatbot
     */
    init() {
        if (this.isInitialized) return;
        
        const chatWidget = document.getElementById('chatbotWidget');
        if (chatWidget) {
            this.isInitialized = true;
            // Chatbot is ready
        }
    },

    /**
     * Open chatbot
     */
    open() {
        const widget = document.getElementById('chatbotWidget');
        if (widget) {
            widget.classList.add('open');
            this.isOpen = true;
            
            // Send welcome message if first open
            if (document.getElementById('chatMessages').children.length === 0) {
                this.addMessage('bot', '👋 Welcome to Smart Restaurant!\n\nI can help with:\n🍽️ Menu & Food\n🤖 Robot Delivery\n💳 Payments\n📞 Contact Info\n⏰ Hours\n📶 WiFi\n\nWhat can I help you with?');
            }
            
            document.getElementById('chatInput').focus();
        }
    },

    /**
     * Close chatbot
     */
    close() {
        const widget = document.getElementById('chatbotWidget');
        if (widget) {
            widget.classList.remove('open');
            this.isOpen = false;
        }
    },

    /**
     * Add message to chat
     */
    addMessage(role, text) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-message ${role}`;
        msgDiv.innerHTML = `<span>${this.escapeHtml(text)}</span>`;
        
        messagesContainer.appendChild(msgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    },

    /**
     * Escape HTML characters
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    },

    /**
     * Get random response from array
     */
    getRandomResponse(arr) {
        return arr[Math.floor(Math.random() * arr.length)];
    },

    /**
     * Process user query
     */
    processQuery(userMessage) {
        const query = userMessage.toLowerCase().trim();

        // Check each response category
        for (const [category, data] of Object.entries(this.responses)) {
            for (const keyword of data.keywords) {
                if (query.includes(keyword)) {
                    return this.getRandomResponse(data.responses);
                }
            }
        }

        // Handle common greetings
        if (query === 'hi' || query === 'hello' || query === 'hey') {
            return 'Hey there! 👋 How can I help you today?';
        }
        if (query === 'thanks' || query === 'thank you' || query === 'tq') {
            return 'You\'re welcome! 😊 Anything else I can help with?';
        }
        if (query === 'help' || query === '?') {
            return 'I can help with:\n🍽️ Menu Info\n🤖 Delivery Details\n💳 Payment Methods\n📞 Contact Us\n⏰ Restaurant Hours\n📶 WiFi Password\n\nJust ask!';
        }

        // Fallback
        return '🤔 I\'m not sure about that. Try asking about:\n🍽️ Food & Menu\n🤖 Robot Delivery\n💳 Payments\n📞 Contact\n⏰ Hours\n📶 WiFi\n\nOr type "help" 😊';
    },

    /**
     * Handle user message
     */
    handleMessage() {
        const input = document.getElementById('chatInput');
        const userMessage = input.value.trim();

        if (!userMessage) return;

        // Add user message
        this.addMessage('user', userMessage);
        input.value = '';

        // Simulate thinking time and send response
        setTimeout(() => {
            const botResponse = this.processQuery(userMessage);
            this.addMessage('bot', botResponse);
        }, 500);
    },

    /**
     * Suggest food based on time
     */
    suggestFood() {
        const hour = new Date().getHours();
        let suggestion = '';

        if (hour >= 5 && hour < 11) {
            suggestion = '☀️ Breakfast Special!\n🥞 Mandazi (KES 50)\n☕ Chai (KES 50)\n🍳 Eggs & Bread (KES 100)';
        } else if (hour >= 11 && hour < 13) {
            suggestion = '🍽️ Lunch Specials!\n🍖 Nyama Choma (KES 450)\n🌾 Ugali with Sukuma Wiki (KES 150)\n🍗 Chicken Stew (KES 350)';
        } else if (hour >= 13 && hour < 17) {
            suggestion = '🍪 Afternoon Snacks!\n🥟 Samosa (KES 60)\n☕ Coffee (KES 80)\n🧁 Pastry (KES 100)';
        } else {
            suggestion = '🌙 Dinner Specials!\n🐟 Grilled Fish (KES 550)\n🍖 Nyama Choma (KES 450)\n🍝 Pasta (KES 300)';
        }

        this.addMessage('bot', suggestion);
    }
};

/**
 * Smooth scroll to section
 */
function scrollToSection(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Close modal on Escape key
 */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('trackModal');
        if (modal && modal.style.display !== 'none') {
            modal.style.display = 'none';
            if (window.orderTracker) {
                window.orderTracker.stopTracking();
            }
        }
    }
});

/**
 * Close modal on outside click
 */
window.addEventListener('click', (event) => {
    const modal = document.getElementById('trackModal');
    if (event.target === modal) {
        modal.style.display = 'none';
        if (window.orderTracker) {
            window.orderTracker.stopTracking();
        }
    }
});

/**
 * Initialize on DOM ready
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize chatbot
    chatbot.init();

    // Setup smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Add keyboard shortcut to open chatbot (Ctrl+Shift+C)
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.shiftKey && e.code === 'KeyC') {
            e.preventDefault();
            if (chatbot.isOpen) {
                chatbot.close();
            } else {
                chatbot.open();
            }
        }
    });
});

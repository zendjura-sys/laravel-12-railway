using System.Collections.Generic;

namespace Tycoon.Droid
{
    // Эмодзи-иконки для объектов игры (Android отображает их нативно)
    public static class Icons
    {
        static readonly Dictionary<string, string> map = new Dictionary<string, string>
        {
            // ресурсы
            { "wheat", "🌾" }, { "wood", "🪵" }, { "bread", "🍞" }, { "ore", "🪨" }, { "furniture", "🪑" },
            { "oil", "🛢️" }, { "steel", "🔩" }, { "fuel", "⛽" }, { "chips", "💾" }, { "car", "🚗" },
            // бизнесы
            { "farm", "🚜" }, { "sawmill", "🪓" }, { "mine", "⛏️" }, { "oilrig", "🏗️" },
            { "bakery", "🥖" }, { "furnfab", "🛋️" }, { "steelmil", "🏭" }, { "refinery", "⚗️" },
            { "electro", "🔌" }, { "autofab", "🚙" },
            { "kiosk", "🌯" }, { "cafe", "☕" }, { "shop", "🏪" }, { "rest", "🍽️" },
            { "itstudio", "👨‍💻" }, { "bankbiz", "🏛️" }, { "megacorp", "🌐" },
            // отрасли
            { "it", "💻" }, { "retail", "🛒" }, { "food", "🍔" }, { "energy", "⚡" }, { "fin", "💼" },
            // акции
            { "KOLS", "🌾" }, { "TAIG", "🌲" }, { "STAL", "🔩" }, { "NEFT", "🛢️" },
            { "BANK", "🏦" }, { "MOTR", "🚗" }, { "KREM", "💻" }, { "BLOK", "🪙" },
        };

        public static string Of(string id, string fallback = "💼")
        {
            string e;
            return id != null && map.TryGetValue(id, out e) ? e : fallback;
        }
    }
}

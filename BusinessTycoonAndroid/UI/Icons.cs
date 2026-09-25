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
            // товары магазина
            { "laptop", "💻" }, { "server", "🖥️" }, { "terminal", "📊" },
            { "pos", "🧾" }, { "shelving", "🗄️" }, { "fridge", "🧊" }, { "kitchen", "🍳" }, { "coffee", "☕" },
            { "van", "🚐" }, { "truck", "🚛" }, { "forklift", "🏗️" },
            { "cnc", "⚙️" }, { "conveyor", "🏭" },
            { "solar", "☀️" }, { "transformer", "⚡" }, { "turbine", "🌀" },
            { "office_s", "🏢" }, { "office_b", "🏬" }, { "office_a", "🏙️" }, { "hq", "🌆" },
            { "warehouse", "📦" }, { "plant", "🏭" },
            { "lic_bank", "📜" }, { "vault", "🔐" },
            { "modkit", "🧰" }, { "course", "📘" }, { "iso", "🏅" }, { "mba", "🎓" }, { "club", "💎" }, { "board", "👔" },
            // отрасли
            { "it", "💻" }, { "retail", "🛒" }, { "food", "🍔" }, { "logistic", "🚚" }, { "factory", "🏭" },
            { "energy", "⚡" }, { "finance", "🏦" },
            // акции
            { "YBLK", "🍎" }, { "MKSF", "🪟" }, { "GUGL", "🔎" }, { "NVDM", "🎮" }, { "METV", "🥽" }, { "SMSG", "📱" },
            { "YNDR", "🧭" }, { "NTFX", "🎬" }, { "KREM", "💻" },
            { "GZPR", "🔥" }, { "LKOL", "🛢️" }, { "RSNF", "⛽" }, { "RKSH", "🐚" }, { "EKSN", "🛢️" }, { "NRNK", "⛏️" },
            { "NEFT", "🛢️" }, { "TAIG", "🌲" },
            { "SBRK", "🏦" }, { "TNKF", "💳" }, { "JPMS", "💼" }, { "BRKH", "🧓" }, { "VIZN", "💳" }, { "BANK", "🏦" },
            { "AMZK", "📦" }, { "ALIB", "🛍️" }, { "MGNK", "🧲" }, { "KOKA", "🥤" }, { "MKDN", "🍟" }, { "NAIK", "👟" },
            { "KOLS", "🌾" },
            { "TSLO", "🔋" }, { "TOYO", "🚗" }, { "BOIN", "✈️" }, { "AERF", "🛫" }, { "RZDL", "🚆" }, { "STAL", "🔩" },
            { "MOTR", "🚙" },
            { "BLOK", "🪙" }, { "BTKN", "₿" },
        };

        public static string Of(string id, string fallback = "💼")
        {
            string e;
            return id != null && map.TryGetValue(id, out e) ? e : fallback;
        }
    }
}

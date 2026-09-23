// Статические игровые данные: ресурсы, бизнесы, акции, отрасли и константы баланса.
// Этот слой не зависит от UnityEngine, поэтому его можно тестировать обычным компилятором C#.
namespace Tycoon
{
    public enum BizCategory { Raw, Production, Service }

    public class ResDef
    {
        public readonly string Id, Name, Abbr, Color;
        public readonly double Price;
        public ResDef(string id, string name, string abbr, string color, double price)
        {
            Id = id; Name = name; Abbr = abbr; Color = color; Price = price;
        }
    }

    public class BizDef
    {
        public readonly string Id, Name, Abbr, Color;
        public readonly BizCategory Cat;
        public readonly double Cost, Growth;
        public readonly double Cash;          // $/с на одну точку (для услуг)
        public readonly int OutRes = -1;      // индекс производимого ресурса
        public readonly double OutQ;          // единиц/с на одну точку
        public readonly int[] InRes = new int[0];
        public readonly double[] InQ = new double[0];

        public bool IsService { get { return OutRes < 0; } }

        BizDef(string id, BizCategory cat, string name, string abbr, string color, double cost, double growth)
        {
            Id = id; Cat = cat; Name = name; Abbr = abbr; Color = color; Cost = cost; Growth = growth;
        }

        public static BizDef Service(string id, string name, string abbr, string color, double cost, double growth, double cash)
        {
            return new BizDef(id, BizCategory.Service, name, abbr, color, cost, growth, cash);
        }

        BizDef(string id, BizCategory cat, string name, string abbr, string color, double cost, double growth, double cash)
            : this(id, cat, name, abbr, color, cost, growth)
        {
            Cash = cash;
        }

        BizDef(string id, BizCategory cat, string name, string abbr, string color, double cost, double growth,
               int outRes, double outQ, int[] inRes, double[] inQ)
            : this(id, cat, name, abbr, color, cost, growth)
        {
            OutRes = outRes; OutQ = outQ; InRes = inRes; InQ = inQ;
        }

        public static BizDef Maker(string id, BizCategory cat, string name, string abbr, string color, double cost, double growth,
                                   string outRes, double outQ, params object[] inputs)
        {
            var n = inputs.Length / 2;
            var ir = new int[n];
            var iq = new double[n];
            for (int i = 0; i < n; i++)
            {
                ir[i] = GameData.ResIndex((string)inputs[i * 2]);
                iq[i] = System.Convert.ToDouble(inputs[i * 2 + 1]);
            }
            return new BizDef(id, cat, name, abbr, color, cost, growth, GameData.ResIndex(outRes), outQ, ir, iq);
        }
    }

    public class StockDef
    {
        public readonly string Ticker, Name, Color;
        public readonly double Price, Vol, Drift, Div;
        public StockDef(string t, string name, string color, double p, double vol, double drift, double div)
        {
            Ticker = t; Name = name; Color = color; Price = p; Vol = vol; Drift = drift; Div = div;
        }
    }

    public class IndustryDef
    {
        public readonly string Id, Name, Color;
        public readonly double Base, Hire, Vol;
        public IndustryDef(string id, string name, string color, double b, double hire, double vol)
        {
            Id = id; Name = name; Color = color; Base = b; Hire = hire; Vol = vol;
        }
    }

    public static class GameData
    {
        public static readonly ResDef[] Resources =
        {
            new ResDef("wheat",     "Зерно",       "Зр", "#E3B341", 1),
            new ResDef("wood",      "Древесина",   "Др", "#A0703C", 8),
            new ResDef("bread",     "Хлеб",        "Хл", "#D9A066", 7),
            new ResDef("ore",       "Руда",        "Рд", "#8C8C99", 60),
            new ResDef("furniture", "Мебель",      "Мб", "#C0843F", 190),
            new ResDef("oil",       "Нефть",       "Нф", "#3A3F4B", 500),
            new ResDef("steel",     "Сталь",       "Ст", "#9FB4C7", 1100),
            new ResDef("fuel",      "Топливо",     "Тп", "#E0703A", 6000),
            new ResDef("chips",     "Электроника", "Эл", "#3FB27F", 100000),
            new ResDef("car",       "Автомобили",  "Ав", "#D6454F", 1.1e7),
        };

        public static int ResIndex(string id)
        {
            for (int i = 0; i < Resources.Length; i++) if (Resources[i].Id == id) return i;
            throw new System.ArgumentException("Unknown resource " + id);
        }

        // Ресурсы, отсортированные по цене — лестница материалов для улучшений
        public static readonly int[] MatOrder = BuildMatOrder();
        static int[] BuildMatOrder()
        {
            var idx = new int[Resources.Length];
            for (int i = 0; i < idx.Length; i++) idx[i] = i;
            System.Array.Sort(idx, (a, b) => Resources[a].Price.CompareTo(Resources[b].Price));
            return idx;
        }

        public static readonly BizDef[] Businesses =
        {
            BizDef.Maker("farm",     BizCategory.Raw, "Ферма",          "Фе", "#7CB342", 15,    1.12, "wheat", 0.5),
            BizDef.Maker("sawmill",  BizCategory.Raw, "Лесопилка",      "Лп", "#8D6E63", 250,   1.13, "wood",  0.5),
            BizDef.Maker("mine",     BizCategory.Raw, "Рудник",         "Ру", "#78909C", 3000,  1.13, "ore",   0.4),
            BizDef.Maker("oilrig",   BizCategory.Raw, "Нефтяная вышка", "Нв", "#455A64", 40000, 1.14, "oil",   0.5),

            BizDef.Maker("bakery",   BizCategory.Production, "Пекарня",                "Пк", "#FFB74D", 500,   1.13, "bread",     1,   "wheat", 2),
            BizDef.Maker("furnfab",  BizCategory.Production, "Мебельная фабрика",      "Мф", "#A1887F", 8000,  1.13, "furniture", 0.5, "wood", 2),
            BizDef.Maker("steelmil", BizCategory.Production, "Металлургический завод", "Мз", "#90A4AE", 1e5,   1.14, "steel",     1,   "ore", 2),
            BizDef.Maker("refinery", BizCategory.Production, "НПЗ",                    "НП", "#FF7043", 5e5,   1.14, "fuel",      1,   "oil", 2),
            BizDef.Maker("electro",  BizCategory.Production, "Завод электроники",      "Зэ", "#26A69A", 5e6,   1.15, "chips",     0.5, "steel", 1, "oil", 1),
            BizDef.Maker("autofab",  BizCategory.Production, "Автозавод",              "Аз", "#E53935", 1e8,   1.15, "car",       0.1, "steel", 2, "chips", 1, "fuel", 1),

            BizDef.Service("kiosk",    "Киоск с шаурмой",           "Кш", "#FFA726", 100,   1.12, 1.2),
            BizDef.Service("cafe",     "Кофейня",                   "Кф", "#8D6E63", 2000,  1.13, 12),
            BizDef.Service("shop",     "Супермаркет",               "См", "#42A5F5", 25000, 1.13, 110),
            BizDef.Service("rest",     "Ресторан",                  "Рс", "#EC407A", 3e5,   1.14, 1000),
            BizDef.Service("itstudio", "IT-студия",                 "IT", "#5C6BC0", 4e6,   1.14, 11000),
            BizDef.Service("bankbiz",  "Частный банк",              "Бк", "#26C6DA", 6e7,   1.15, 130000),
            BizDef.Service("megacorp", "Технологическая корпорация","ТК", "#AB47BC", 1e9,   1.15, 1.8e6),
        };

        public static readonly string[] CategoryNames = { "Добыча сырья", "Производство", "Услуги" };

        // Каждая отметка количества удваивает производство
        public static readonly int[] Milestones = { 10, 25, 50, 100, 150, 200, 300, 400, 500 };
        public const int MaxBizLevel = 10;

        // Акции: цена, волатильность за секунду, дрейф справедливой цены за секунду, дивиденды за минуту
        public static readonly StockDef[] Stocks =
        {
            new StockDef("KOLS", "Агрохолдинг «Колос»",     "#E3B341", 45,  0.006, 0.00004, 0.004),
            new StockDef("TAIG", "Лесная компания «Тайга»", "#4CAF50", 30,  0.008, 0.00005, 0.003),
            new StockDef("STAL", "СтальПром",               "#90A4AE", 210, 0.010, 0.00005, 0.003),
            new StockDef("NEFT", "Нефтегаз Холдинг",        "#546E7A", 380, 0.011, 0.00006, 0.005),
            new StockDef("BANK", "Капитал Банк",            "#26C6DA", 260, 0.009, 0.00005, 0.004),
            new StockDef("MOTR", "Моторс Групп",            "#E53935", 150, 0.013, 0.00007, 0.002),
            new StockDef("KREM", "Кремний Тех",             "#5C6BC0", 950, 0.018, 0.00010, 0),
            new StockDef("BLOK", "БлокЧейн Про",            "#FFB300", 12,  0.035, 0.00012, 0),
        };
        public const double StockTheta = 0.01;   // сила возврата к справедливой цене
        public const double TradeFee = 0.002;    // комиссия брокера
        public const int OptSize = 10;           // акций в одном опционном контракте
        public const double OptSpread = 0.05;    // спред маркетмейкера
        public static readonly int[] OptExpiries = { 60, 300, 900 };
        public static readonly double[] OptStrikes = { 0.9, 0.95, 1.0, 1.05, 1.1 };

        public static readonly IndustryDef[] Industries =
        {
            new IndustryDef("it",     "IT и софт",  "#5C6BC0", 3,   500, 0.020),
            new IndustryDef("retail", "Ритейл",     "#42A5F5", 2.5, 400, 0.010),
            new IndustryDef("food",   "Общепит",    "#FFA726", 2,   300, 0.008),
            new IndustryDef("energy", "Энергетика", "#FDD835", 4,   750, 0.012),
            new IndustryDef("fin",    "Финансы",    "#26A69A", 3.5, 650, 0.015),
        };
        public static readonly double[] CompanyFoundCost = { 5000, 5e5, 5e7 };
        public static readonly double[] CompanyScale = { 1, 80, 6000 };
        public const int CompanyShares = 10000;
        public const double IpoMinValuation = 50000;   // × масштаб компании
        public static readonly double[] IpoPercents = { 0.10, 0.25, 0.49 };

        public const double DepositRate = 0.002 / 60;  // 0.2% в минуту
        public const double LoanRate = 0.005 / 60;     // 0.5% в минуту

        public const double OfflineCapSeconds = 8 * 3600;
        public const double StartMoney = 20;
    }
}

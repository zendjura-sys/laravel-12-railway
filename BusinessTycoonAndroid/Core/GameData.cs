// Статические игровые данные: ресурсы, бизнесы, акции, магазин, отрасли, формы компаний, уровни.
// Слой не зависит от Android, его можно тестировать обычным компилятором C#.
namespace Tycoon
{
    public enum BizCategory { Raw, Production, Service }

    public class ResDef
    {
        public readonly string Id, Name;
        public readonly double Price;
        public ResDef(string id, string name, double price) { Id = id; Name = name; Price = price; }
    }

    public class BizDef
    {
        public readonly string Id, Name;
        public readonly BizCategory Cat;
        public readonly int ReqLevel;
        public readonly double Cost, Growth;
        public readonly double Cash;          // $/с на одну точку (услуги)
        public readonly int OutRes = -1;      // производимый ресурс
        public readonly double OutQ;          // единиц/с на точку
        public readonly int[] InRes = new int[0];
        public readonly double[] InQ = new double[0];

        public bool IsService { get { return OutRes < 0; } }

        BizDef(string id, BizCategory cat, string name, int lvl, double cost, double growth)
        {
            Id = id; Cat = cat; Name = name; ReqLevel = lvl; Cost = cost; Growth = growth;
        }

        BizDef(string id, string name, int lvl, double cost, double growth, double cash)
            : this(id, BizCategory.Service, name, lvl, cost, growth) { Cash = cash; }

        BizDef(string id, BizCategory cat, string name, int lvl, double cost, double growth,
               int outRes, double outQ, int[] inRes, double[] inQ)
            : this(id, cat, name, lvl, cost, growth)
        {
            OutRes = outRes; OutQ = outQ; InRes = inRes; InQ = inQ;
        }

        public static BizDef Service(string id, string name, int lvl, double cost, double growth, double cash)
        {
            return new BizDef(id, name, lvl, cost, growth, cash);
        }

        public static BizDef Maker(string id, BizCategory cat, string name, int lvl, double cost, double growth,
                                   string outRes, double outQ, params object[] inputs)
        {
            int n = inputs.Length / 2;
            var ir = new int[n];
            var iq = new double[n];
            for (int i = 0; i < n; i++)
            {
                ir[i] = GameData.ResIndex((string)inputs[i * 2]);
                iq[i] = System.Convert.ToDouble(inputs[i * 2 + 1]);
            }
            return new BizDef(id, cat, name, lvl, cost, growth, GameData.ResIndex(outRes), outQ, ir, iq);
        }
    }

    public enum Sector { Tech, Energy, Finance, Consumer, Industry, Crypto }

    public class StockDef
    {
        public readonly string Ticker, Name;
        public readonly Sector Sector;
        public readonly double Price, Vol, Drift, Div;
        public StockDef(string t, string name, Sector sector, double p, double vol, double drift, double div)
        {
            Ticker = t; Name = name; Sector = sector; Price = p; Vol = vol; Drift = drift; Div = div;
        }
    }

    public enum ItemCat { Tech, Trade, Transport, Production, Energy, Realty, License, Growth }

    // Товар магазина: оборудование, транспорт, недвижимость, лицензии
    public class ItemDef
    {
        public readonly string Id, Name, Desc;
        public readonly ItemCat Cat;
        public readonly double Price;
        public readonly int ReqLevel;
        public ItemDef(string id, ItemCat cat, string name, double price, int lvl, string desc)
        {
            Id = id; Cat = cat; Name = name; Price = price; ReqLevel = lvl; Desc = desc;
        }
    }

    // Отрасль собственной компании
    public class IndustryDef
    {
        public readonly string Id, Name;
        public readonly int ReqLevel;
        public readonly double RevPerStaff;   // выручка сотрудника, $/с (для ИП)
        public readonly double Wage;          // зарплата сотрудника, $/с
        public readonly double Fixed;         // постоянные расходы (аренда, коммуналка), $/с
        public readonly double Demand;        // ёмкость рынка без маркетинга, $/с
        public readonly double HireFee;       // затраты на подбор сотрудника
        public readonly double Vol;           // волатильность акций после IPO
        public readonly int[] StartItems;     // пары (товар, количество) для открытия
        public readonly int SeatItem;         // оборудование рабочего места
        public readonly int SeatsPer;         // мест на единицу оборудования
        public readonly int BoostItem;        // оборудование, повышающее выручку
        public readonly double BoostPct;
        public readonly int BoostMax;

        public IndustryDef(string id, string name, int lvl, double rev, double wage, double fix, double demand,
                           double hire, double vol, object[] start, string seat, int seatsPer,
                           string boost, double boostPct, int boostMax)
        {
            Id = id; Name = name; ReqLevel = lvl; RevPerStaff = rev; Wage = wage; Fixed = fix; Demand = demand;
            HireFee = hire; Vol = vol;
            StartItems = new int[start.Length];
            for (int i = 0; i < start.Length; i += 2)
            {
                StartItems[i] = GameData.ItemIndex((string)start[i]);
                StartItems[i + 1] = (int)start[i + 1];
            }
            SeatItem = GameData.ItemIndex(seat);
            SeatsPer = seatsPer;
            BoostItem = GameData.ItemIndex(boost);
            BoostPct = boostPct;
            BoostMax = boostMax;
        }
    }

    // Организационно-правовая форма: ИП → ООО → АО → ПАО → Корпорация
    public class FormDef
    {
        public readonly string Short, Name, Desc;
        public readonly int ReqLevel;
        public readonly double Fee;          // госпошлина, уставный капитал, аудит
        public readonly double Mult;         // масштаб бизнеса
        public readonly int MaxStaff;
        public readonly int MinStaff;        // штат для перехода в эту форму
        public readonly double MinValuation; // оценка для перехода
        public readonly int[] Items;         // пары (товар, количество)
        public readonly double TaxRate;
        public readonly bool TaxOnRevenue;   // УСН «доходы» для ИП

        public FormDef(string shortName, string name, int lvl, double fee, double mult, int maxStaff, int minStaff,
                       double minVal, double tax, bool taxOnRevenue, string desc, params object[] items)
        {
            Short = shortName; Name = name; ReqLevel = lvl; Fee = fee; Mult = mult; MaxStaff = maxStaff;
            MinStaff = minStaff; MinValuation = minVal; TaxRate = tax; TaxOnRevenue = taxOnRevenue; Desc = desc;
            Items = new int[items.Length];
            for (int i = 0; i < items.Length; i += 2)
            {
                Items[i] = GameData.ItemIndex((string)items[i]);
                Items[i + 1] = (int)items[i + 1];
            }
        }
    }

    public static class GameData
    {
        // ---------------- Ресурсы ----------------
        public static readonly ResDef[] Resources =
        {
            new ResDef("wheat",     "Зерно",       1),
            new ResDef("wood",      "Древесина",   8),
            new ResDef("bread",     "Хлеб",        7),
            new ResDef("ore",       "Руда",        60),
            new ResDef("furniture", "Мебель",      190),
            new ResDef("oil",       "Нефть",       500),
            new ResDef("steel",     "Сталь",       1100),
            new ResDef("fuel",      "Топливо",     6000),
            new ResDef("chips",     "Электроника", 100000),
            new ResDef("car",       "Автомобили",  1.1e7),
        };

        public static int ResIndex(string id)
        {
            for (int i = 0; i < Resources.Length; i++) if (Resources[i].Id == id) return i;
            throw new System.ArgumentException("Unknown resource " + id);
        }

        public static readonly int[] MatOrder = BuildMatOrder();
        static int[] BuildMatOrder()
        {
            var idx = new int[Resources.Length];
            for (int i = 0; i < idx.Length; i++) idx[i] = i;
            System.Array.Sort(idx, (a, b) => Resources[a].Price.CompareTo(Resources[b].Price));
            return idx;
        }

        // ---------------- Бизнесы (открываются по уровню) ----------------
        public static readonly BizDef[] Businesses =
        {
            BizDef.Maker("farm",     BizCategory.Raw, "Ферма",          1,  15,    1.12, "wheat", 0.5),
            BizDef.Maker("sawmill",  BizCategory.Raw, "Лесопилка",      2,  250,   1.13, "wood",  0.5),
            BizDef.Maker("mine",     BizCategory.Raw, "Рудник",         5,  3000,  1.13, "ore",   0.4),
            BizDef.Maker("oilrig",   BizCategory.Raw, "Нефтяная вышка", 10, 40000, 1.14, "oil",   0.5),

            BizDef.Maker("bakery",   BizCategory.Production, "Пекарня",                4,  500,  1.13, "bread",     1,   "wheat", 2),
            BizDef.Maker("furnfab",  BizCategory.Production, "Мебельная фабрика",      8,  8000, 1.13, "furniture", 0.5, "wood", 2),
            BizDef.Maker("steelmil", BizCategory.Production, "Металлургический завод", 14, 1e5,  1.14, "steel",     1,   "ore", 2),
            BizDef.Maker("refinery", BizCategory.Production, "НПЗ",                    17, 5e5,  1.14, "fuel",      1,   "oil", 2),
            BizDef.Maker("electro",  BizCategory.Production, "Завод электроники",      22, 5e6,  1.15, "chips",     0.5, "steel", 1, "oil", 1),
            BizDef.Maker("autofab",  BizCategory.Production, "Автозавод",              28, 1e8,  1.15, "car",       0.1, "steel", 2, "chips", 1, "fuel", 1),

            BizDef.Service("kiosk",    "Киоск с шаурмой",            1,  100,   1.12, 1.2),
            BizDef.Service("cafe",     "Кофейня",                    3,  2000,  1.13, 12),
            BizDef.Service("shop",     "Супермаркет",                7,  25000, 1.13, 110),
            BizDef.Service("rest",     "Ресторан",                   12, 3e5,   1.14, 1000),
            BizDef.Service("itstudio", "IT-студия",                  19, 4e6,   1.14, 11000),
            BizDef.Service("bankbiz",  "Частный банк",               25, 6e7,   1.15, 130000),
            BizDef.Service("megacorp", "Технологическая корпорация", 32, 1e9,   1.15, 1.8e6),
        };

        public static readonly string[] CategoryNames = { "Добыча сырья", "Производство", "Услуги" };
        public static readonly int[] Milestones = { 10, 25, 50, 100, 150, 200, 300, 400, 500 };
        public const int MaxBizLevel = 10;

        // ---------------- Биржа ----------------
        // Вымышленные компании и «пародии» на известные бренды (названия изменены)
        public static readonly StockDef[] Stocks =
        {
            // Технологии
            new StockDef("YBLK", "Яблоко Инк.",            Sector.Tech, 190, 0.009, 0.00008, 0.0005),
            new StockDef("MKSF", "МикроСофтик",             Sector.Tech, 410, 0.008, 0.00008, 0.0008),
            new StockDef("GUGL", "Гугол Холдингс",          Sector.Tech, 170, 0.010, 0.00009, 0.0004),
            new StockDef("NVDM", "Нвидиум",                 Sector.Tech, 120, 0.018, 0.00014, 0.0001),
            new StockDef("METV", "Метаверсия",              Sector.Tech, 480, 0.014, 0.00009, 0.0004),
            new StockDef("SMSG", "Самсунгер Электроникс",   Sector.Tech, 60,  0.010, 0.00006, 0.002),
            new StockDef("YNDR", "Яндексон",                Sector.Tech, 45,  0.013, 0.00008, 0),
            new StockDef("NTFX", "Нетфликер",               Sector.Tech, 650, 0.015, 0.00008, 0),
            new StockDef("KREM", "Кремний Тех",             Sector.Tech, 950, 0.018, 0.00010, 0),
            // Энергетика и сырьё
            new StockDef("GZPR", "ГазПромышленник",         Sector.Energy, 160, 0.010, 0.00003, 0.006),
            new StockDef("LKOL", "ЛукОйлер",                Sector.Energy, 70,  0.009, 0.00004, 0.007),
            new StockDef("RSNF", "РосНефтяник",             Sector.Energy, 55,  0.010, 0.00003, 0.006),
            new StockDef("RKSH", "Ракушка Ойл",             Sector.Energy, 68,  0.008, 0.00003, 0.005),
            new StockDef("EKSN", "ЭкссонМобилус",           Sector.Energy, 110, 0.008, 0.00004, 0.004),
            new StockDef("NRNK", "НорНикелевый",            Sector.Energy, 130, 0.011, 0.00004, 0.005),
            new StockDef("NEFT", "Нефтегаз Холдинг",        Sector.Energy, 380, 0.011, 0.00006, 0.005),
            new StockDef("TAIG", "Лесная компания «Тайга»", Sector.Energy, 30,  0.008, 0.00005, 0.003),
            // Финансы
            new StockDef("SBRK", "СберКапиталъ",            Sector.Finance, 300, 0.008, 0.00005, 0.007),
            new StockDef("TNKF", "Т-Финтех",                Sector.Finance, 35,  0.014, 0.00008, 0.003),
            new StockDef("JPMS", "Джей-Пи Морганс",         Sector.Finance, 200, 0.008, 0.00005, 0.004),
            new StockDef("BRKH", "Беркшир Хатауэйн",        Sector.Finance, 450, 0.006, 0.00006, 0),
            new StockDef("VIZN", "ВизаНет Платежи",         Sector.Finance, 280, 0.007, 0.00007, 0.002),
            new StockDef("BANK", "Капитал Банк",            Sector.Finance, 260, 0.009, 0.00005, 0.004),
            // Потребительский сектор
            new StockDef("AMZK", "Амазонка",                Sector.Consumer, 180, 0.011, 0.00009, 0),
            new StockDef("ALIB", "Алибабай Групп",          Sector.Consumer, 85,  0.014, 0.00006, 0.002),
            new StockDef("MGNK", "Магнитка Ритейл",         Sector.Consumer, 50,  0.009, 0.00004, 0.006),
            new StockDef("KOKA", "Кока-Кулер",              Sector.Consumer, 62,  0.005, 0.00003, 0.005),
            new StockDef("MKDN", "МакДональдсон",           Sector.Consumer, 290, 0.006, 0.00004, 0.004),
            new StockDef("NAIK", "Найкер Спорт",            Sector.Consumer, 95,  0.008, 0.00004, 0.003),
            new StockDef("KOLS", "Агрохолдинг «Колос»",     Sector.Consumer, 45,  0.006, 0.00004, 0.004),
            // Промышленность и транспорт
            new StockDef("TSLO", "Тесло Моторс",            Sector.Industry, 250, 0.020, 0.00012, 0),
            new StockDef("TOYO", "Тоёта Групп",             Sector.Industry, 175, 0.007, 0.00004, 0.004),
            new StockDef("BOIN", "Боингер Аэро",            Sector.Industry, 190, 0.013, 0.00005, 0),
            new StockDef("AERF", "Аэрофлотик",              Sector.Industry, 12,  0.012, 0.00003, 0.002),
            new StockDef("RZDL", "РЖД-Логистик",            Sector.Industry, 28,  0.008, 0.00004, 0.004),
            new StockDef("STAL", "СтальПром",               Sector.Industry, 210, 0.010, 0.00005, 0.003),
            new StockDef("MOTR", "Моторс Групп",            Sector.Industry, 150, 0.013, 0.00007, 0.002),
            // Крипто и спекуляции
            new StockDef("BLOK", "БлокЧейн Про",            Sector.Crypto, 12,  0.035, 0.00012, 0),
            new StockDef("BTKN", "БиткоинТраст",            Sector.Crypto, 60,  0.040, 0.00015, 0),
        };

        public static readonly string[] SectorNames = { "Технологии", "Энергетика", "Финансы", "Потребительский", "Промышленность", "Крипто" };

        public const double StockTheta = 0.01;
        public const double TradeFee = 0.002;
        public const int OptSize = 10;
        public const double OptSpread = 0.05;
        public static readonly int[] OptExpiries = { 60, 300, 900 };
        public static readonly double[] OptStrikes = { 0.9, 0.95, 1.0, 1.05, 1.1 };

        // ---------------- Магазин ----------------
        public static readonly ItemDef[] Items =
        {
            new ItemDef("laptop",     ItemCat.Tech, "Ноутбук",                 800,     1,  "Рабочее место сотрудника IT-компании"),
            new ItemDef("server",     ItemCat.Tech, "Сервер",                  12000,   5,  "+15% к выручке IT, +10% банку"),
            new ItemDef("terminal",   ItemCat.Tech, "Биржевой терминал",       60000,   10, "Рабочее место трейдера (3 чел.)"),

            new ItemDef("pos",        ItemCat.Trade, "Онлайн-касса",           600,     2,  "Обязательна для торговли и общепита, 4 кассира"),
            new ItemDef("shelving",   ItemCat.Trade, "Торговые стеллажи",      2000,    2,  "Нужны для открытия магазина"),
            new ItemDef("fridge",     ItemCat.Trade, "Холодильная витрина",    3500,    3,  "+6% к выручке магазина"),
            new ItemDef("kitchen",    ItemCat.Trade, "Профессиональная кухня", 25000,   4,  "Рабочие места для 8 поваров"),
            new ItemDef("coffee",     ItemCat.Trade, "Кофемашина",             4000,    4,  "+8% к выручке общепита"),

            new ItemDef("van",        ItemCat.Transport, "Грузовой фургон",    30000,   6,  "Доставка для малого бизнеса"),
            new ItemDef("truck",      ItemCat.Transport, "Фура",               120000,  9,  "Рабочее место для 3 логистов"),
            new ItemDef("forklift",   ItemCat.Transport, "Погрузчик",          40000,   9,  "+10% к выручке логистики"),

            new ItemDef("cnc",        ItemCat.Production, "Станок ЧПУ",        250000,  13, "Рабочее место для 5 рабочих"),
            new ItemDef("conveyor",   ItemCat.Production, "Конвейерная линия", 1.5e6,   16, "+25% к выручке производства"),

            new ItemDef("solar",      ItemCat.Energy, "Солнечная электростанция",    2e6,  18, "Генерирующая мощность"),
            new ItemDef("transformer",ItemCat.Energy, "Трансформаторная подстанция", 5e6,  18, "Рабочее место для 20 инженеров"),
            new ItemDef("turbine",    ItemCat.Energy, "Газовая турбина",             25e6, 22, "+30% к выручке энергетики"),

            new ItemDef("office_s",   ItemCat.Realty, "Офис в коворкинге",       20000,  5,  "Юридический адрес, нужен для ООО"),
            new ItemDef("office_b",   ItemCat.Realty, "Офис класса B",           4e5,    11, "Нужен для АО и ПАО"),
            new ItemDef("office_a",   ItemCat.Realty, "Офис класса A",           5e6,    20, "Нужен для холдинга"),
            new ItemDef("hq",         ItemCat.Realty, "Штаб-квартира (небоскрёб)", 2.5e8, 28, "Нужна корпорации"),
            new ItemDef("warehouse",  ItemCat.Realty, "Складской комплекс",      3e5,    9,  "Нужен для логистической компании"),
            new ItemDef("plant",      ItemCat.Realty, "Промышленная площадка",   3e6,    13, "Земля и цеха для завода или станции"),

            new ItemDef("lic_bank",   ItemCat.License, "Банковская лицензия ЦБ", 2e7,    22, "Без неё нельзя открыть банк"),
            new ItemDef("vault",      ItemCat.License, "Банковское хранилище",   2e6,    22, "Нужно для банка"),

            // Развитие: специальные предметы для повышения уровня и модернизации бизнеса
            new ItemDef("modkit",     ItemCat.Growth, "Комплект модернизации",       1e5,   10, "Улучшение бизнеса выше 5-го уровня"),
            new ItemDef("course",     ItemCat.Growth, "Бизнес-курс",                 4e4,   15, "Нужен для повышения до уровней 16–20"),
            new ItemDef("iso",        ItemCat.Growth, "Сертификат качества ISO",     2e6,   20, "Нужен для уровней 21–30"),
            new ItemDef("mba",        ItemCat.Growth, "Диплом MBA",                  5e7,   30, "Нужен для уровней 31–40"),
            new ItemDef("club",       ItemCat.Growth, "Членство в клубе миллиардеров", 2e9, 40, "Нужно для уровней 41–50"),
            new ItemDef("board",      ItemCat.Growth, "Место в совете директоров",   1e11,  50, "Нужно для уровней 51–60"),
        };

        public static readonly string[] ItemCatNames = { "Техника", "Торговля и общепит", "Транспорт", "Производство", "Энергетика", "Недвижимость", "Лицензии", "Развитие" };

        public static int ItemIndex(string id)
        {
            for (int i = 0; i < Items.Length; i++) if (Items[i].Id == id) return i;
            throw new System.ArgumentException("Unknown item " + id);
        }

        // ---------------- Свои компании ----------------
        public static readonly IndustryDef[] Industries =
        {
            new IndustryDef("it",       "IT-компания",        3,  4,   1.5, 0.5, 40,   150,   0.020,
                new object[] { "laptop", 1 },                               "laptop", 1,       "server", 0.15, 10),
            new IndustryDef("retail",   "Розничная торговля", 4,  3,   1.0, 1.0, 45,   100,   0.010,
                new object[] { "pos", 1, "shelving", 2 },                   "pos", 4,          "fridge", 0.06, 10),
            new IndustryDef("food",     "Общепит",            5,  3.5, 1.3, 1.5, 35,   120,   0.008,
                new object[] { "kitchen", 1, "pos", 1 },                    "kitchen", 8,      "coffee", 0.08, 5),
            new IndustryDef("logistic", "Логистика",          9,  12,  4,   5,   150,  500,   0.012,
                new object[] { "truck", 1, "warehouse", 1 },                "truck", 3,        "forklift", 0.10, 10),
            new IndustryDef("factory",  "Производство",       13, 40,  12,  20,  500,  1500,  0.012,
                new object[] { "cnc", 2, "plant", 1 },                      "cnc", 5,          "conveyor", 0.25, 5),
            new IndustryDef("energy",   "Энергетика",         18, 150, 40,  100, 2500, 6000,  0.010,
                new object[] { "solar", 1, "transformer", 1, "plant", 1 },  "transformer", 20, "turbine", 0.30, 5),
            new IndustryDef("finance",  "Банк и инвестиции",  22, 500, 150, 300, 8000, 20000, 0.015,
                new object[] { "lic_bank", 1, "vault", 1, "terminal", 2 },  "terminal", 3,     "server", 0.10, 10),
        };

        public const int FormIP = 0, FormOOO = 1, FormAO = 2, FormPAO = 3, FormCorp = 4;

        public static readonly FormDef[] Forms =
        {
            new FormDef("ИП",   "Индивидуальный предприниматель",      3,  500,   1,    15,      0,    0,    0.06, true,
                "Быстрая регистрация, налог 6% с выручки (УСН). До 15 сотрудников."),
            new FormDef("ООО",  "Общество с ограниченной ответственностью", 6, 15000, 4, 250, 5,    0,    0.20, false,
                "Госпошлина и уставный капитал. Налог 20% с прибыли. До 250 сотрудников.", "office_s", 1),
            new FormDef("АО",   "Акционерное общество (непубличное)",  12, 1e6,   25,   2000,    50,   1e6,  0.20, false,
                "Уставный капитал $1M, выпуск акций. До 2000 сотрудников.", "office_b", 1),
            new FormDef("ПАО",  "Публичное акционерное общество",      16, 5e6,   150,  10000,   150,  2e7,  0.20, false,
                "Аудит, листинг и IPO: акции торгуются на бирже. До 10 000 сотрудников."),
            new FormDef("Корп.", "Корпорация",                         25, 2e8,   1000, 1000000, 1000, 2e9,  0.20, false,
                "Транснациональный масштаб, штаб-квартира. Штат без ограничений.", "hq", 1),
        };

        public const int MaxCompanies = 6;
        public const int CompanyShares = 10000;
        public static readonly double[] IpoPercents = { 0.10, 0.25, 0.49 };

        // Холдинг: управляющая компания над несколькими бизнесами
        public const int HoldingLevel = 28;
        public const double HoldingFee = 5e7;
        public const int HoldingMinCompanies = 3;
        public const double HoldingTax = 0.15;          // консолидированная группа налогоплательщиков
        public const double HoldingBonusPer = 0.05;     // +5% выручки за каждую дочернюю компанию
        public const double HoldingBonusMax = 0.40;
        public static readonly int HoldingItem = ItemIndex("office_a");

        // ---------------- Уровни ----------------
        public const int MaxLevel = 60;
        public const int LevelDeposit = 2, LevelStocks = 3, LevelLoans = 5, LevelOptions = 8;

        // Опыт для перехода с уровня L на L+1 (опыт даётся 1 XP за $50 заработка + действия)
        public static double XpForLevel(int level) { return 150 * System.Math.Pow(2.0, level - 1); }
        public static double LevelReward(int level) { return 15 * System.Math.Pow(1.8, level); }

        // С 15-го уровня для повышения нужен специальный предмет из магазина.
        // Возвращает {индекс товара, количество} для перехода с level на level+1 или null.
        public const int GatedFromLevel = 15;
        public const double XpPerDollar = 1.0 / 50;
        static readonly string[] GateItems = { "course", "iso", "iso", "mba", "mba", "club", "club", "board", "board" };

        public static int[] LevelGate(int level)
        {
            if (level < GatedFromLevel || level >= MaxLevel) return null;
            int tier = (level - GatedFromLevel) / 5;            // 15–19, 20–24, 25–29 ...
            if (tier >= GateItems.Length) tier = GateItems.Length - 1;
            int count = 1 + (level - GatedFromLevel) % 5;       // 1..5 внутри каждой пятёрки
            return new[] { ItemIndex(GateItems[tier]), count };
        }

        // Улучшение бизнеса выше 5-го уровня требует комплектов модернизации
        public const int ModkitFromBizLevel = 5;
        public static readonly int Modkit = ItemIndex("modkit");

        // ---------------- Банк и прочее ----------------
        public const double DepositRate = 0.002 / 60;
        public const double LoanRate = 0.005 / 60;
        public const double OfflineCapSeconds = 8 * 3600;
        public const double StartMoney = 20;
    }
}

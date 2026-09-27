/**
 * Дієслово у правильному роді для текстів на сторінках ("подав"/"подала",
 * "створив"/"створила" тощо). person.gender не вказано — лишається
 * чоловіча форма, той самий текст, що показувався до появи цього поля.
 */
export function verb(person, masculine, feminine) {
    return person?.gender === 'f' ? feminine : masculine;
}

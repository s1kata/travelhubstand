# Support Chat Bot (MVP, no AI)

Единый endpoint для сайта и мобильного приложения:

- `POST /backend/api/support-chat.php`

## Request

```json
{
  "message": "Как оплатить тур?",
  "sessionId": "abc123optional",
  "channel": "site"
}
```

## Response

```json
{
  "success": true,
  "sessionId": "e4f1c2d3a4b5c6d7",
  "intent": "payment",
  "reply": "Оплатить можно онлайн картой...",
  "handoff": false,
  "quickReplies": ["Оплата", "Бронирование", "Документы", "Связаться с менеджером"]
}
```

## Notes

- Без AI: rule-based ответы по ключевым словам.
- Rate limit: 20 сообщений в минуту на IP.
- Логи диалогов: `data/support_chat_logs/<sessionId>.jsonl`.
- Если бот не понимает вопрос, отвечает с предложением связаться с менеджером.
- На сайте менеджер вызывается кнопкой в виджете (`openQuickLeadModal('support-chat')`), fallback — `tel:+78462541656`.


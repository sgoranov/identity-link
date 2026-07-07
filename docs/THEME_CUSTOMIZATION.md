# Theme Customization

In certain environments, you may want to customize the look, feel, and behavior of the application
— including templates, assets, translations, or other environment-specific resources —
without modifying the original application code.
To support this, the application provides a special _**local_theme**_ directory, which is
excluded from version control (.gitignore). This directory allows for environment-specific
theming and overrides, enabling each deployment to maintain its own branding, language, or
structure without affecting shared or upstream code.

Supported customizations include:
- Twig template overrides (local_theme/templates/)
- Public assets (CSS, JS, images) (local_theme/public/)
- Translations and language support (local_theme/translations/)

## Overriding Twig Templates

To override a template:

- Create the corresponding path inside the local_theme/templates directory.
- Copy the original template you want to override.
- Apply your custom changes.

```bash
mkdir -p local_theme/templates/login
cp templates/login/login.html.twig local_theme/templates/login/login.html.twig
```

Any template found in local_theme/templates/ with the same name as the original
will automatically replace it.

## Overriding Public Assets

You can also add your own assets like CSS, JavaScript, or images to _local_theme/public_ directory.
To make this easier, the repository includes a symbolic link named _theme/_ that points to _local_theme/public_.
You can reference theme-specific assets using paths like _/theme/style.css_.

## Overriding Translations

To override or extend translations, use the local_theme/translations directory. This allows you to:
- Override existing translation keys in any language.
- Add new translation keys specific to your environment.
- Introduce support for additional languages not included in the base application.

The translation files in _local_theme/translations/_ follow the standard Symfony format (messages.en.yaml, messages.bg.yaml, etc.),
and will automatically override the default translations if the same keys are defined.

Example:
```bash
local_theme/translations/messages.bg.yaml
```

This flexibility is especially useful when customizing UI wording or adding localized content for specific deployments
without touching shared application files.

This allows each environment to include its own branding or theme without affecting shared code.


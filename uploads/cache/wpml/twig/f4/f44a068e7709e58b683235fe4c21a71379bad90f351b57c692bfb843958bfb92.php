<?php

namespace WPML\Core;

use \WPML\Core\Twig\Environment;
use \WPML\Core\Twig\Error\LoaderError;
use \WPML\Core\Twig\Error\RuntimeError;
use \WPML\Core\Twig\Markup;
use \WPML\Core\Twig\Sandbox\SecurityError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedTagError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFilterError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFunctionError;
use \WPML\Core\Twig\Source;
use \WPML\Core\Twig\Template;

/* layout-reset.twig */
class __TwigTemplate_38e742762addad32c7588c4f44aba7c6938da18c0372e0284949730969d4eb9f extends \WPML\Core\Twig\Template
{
    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        // line 1
        echo "<div class=\"wpml-section\" id=\"wpml_ls_reset\">
\t<div class=\"wpml-section-header\">
\t\t<h3>";
        // line 3
        echo \WPML\Core\twig_escape_filter($this->env, ($context["title"] ?? null), "html", null, true);
        echo "</h3>
\t</div>
\t<div class=\"wpml-section-content\">
\t\t<p>";
        // line 6
        echo ($context["description"] ?? null);
        echo "</p>

\t\t";
        // line 8
        if (($context["theme_config_file"] ?? null)) {
            // line 9
            echo "\t\t\t<p class=\"explanation-text\">";
            echo ($context["explanation_text"] ?? null);
            echo "</p>
\t\t";
        }
        // line 11
        echo "
\t\t<p class=\"buttons-wrap\">
\t\t\t<a
\t\t\t\tclass=\"button-secondary wpml-button base-btn wpml-button--outlined\"
\t\t\t\tonclick=\"if(!confirm('";
        // line 15
        echo \WPML\Core\twig_escape_filter($this->env, ($context["confirmation_message"] ?? null), "html", null, true);
        echo "')) return false;\"
\t\t\t   href=\"";
        // line 16
        echo \WPML\Core\twig_escape_filter($this->env, ($context["restore_page_url"] ?? null), "html", null, true);
        echo "\">";
        echo \WPML\Core\twig_escape_filter($this->env, ($context["restore_button_label"] ?? null), "html", null, true);
        if (($context["theme_config_file"] ?? null)) {
            echo " *";
        }
        echo "</a>
\t\t</p>
\t</div>
</div>
";
    }

    public function getTemplateName()
    {
        return "layout-reset.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  65 => 16,  61 => 15,  55 => 11,  49 => 9,  47 => 8,  42 => 6,  36 => 3,  32 => 1,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("", "layout-reset.twig", "C:\\Users\\Zeeshan Ldninjas\\Local Sites\\the-heart-guide\\app\\public\\wp-content\\plugins\\sitepress-multilingual-cms\\templates\\language-switcher-admin-ui\\layout-reset.twig");
    }
}

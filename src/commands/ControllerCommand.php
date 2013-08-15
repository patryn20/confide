<?php namespace Zizaco\Confide;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ControllerCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'confide:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a controller template that uses Confide.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $app = app();
        $app['view']->addNamespace('confide',substr(__DIR__,0,-8).'views');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $name = $this->prepareName($this->option('name'), $this->option('controller_prefix'));
        $file_name = $this->prepareName($this->option('name'));
        $restful = $this->option('restful');

        $this->line('');
        $this->info( "Controller name: $name".(($restful) ? "\nRESTful: Yes" : '') );
        $message = "An authentication ".(($restful) ? 'RESTful ' : '')."controller template with the name $name.php".
        " will be created in app/controllers directory and will NOT overwrite any ".
        " file.";

        $this->comment( $message );
        $this->line('');

        if ( $this->confirm("Proceed with the controller creation? [Yes|no]") )
        {
            $this->line('');

            $this->info( "Creating $name..." );
            if( $this->createController( $file_name, $controller_prefix, $restful ) )
            {
                $this->info( "$name.php Successfully created!" );
            }
            else{
                $this->error( 
                    "Coudn't create app/controllers/$name.php.\nCheck the".
                    " write permissions within the controllers directory".
                    " or if $name.php already exists. (This command will".
                    " not overwrite any file. delete the existing $name.php".
                    " if you wish to continue)."
                );
            }

            $this->line('');

        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $app = app();

        return array(
            array('name', null, InputOption::VALUE_OPTIONAL, 'Name of the controller.', $app['config']->get('auth.model')),
            array('controller_prefix', null, InputOption::VALUE_OPTIONAL, 'Prefix of the controller.', $app['config']->get('confide.controller_prefix')),
            array('--restful', '-r', InputOption::VALUE_NONE, 'Generate RESTful controller.'),
        );
    }

    /**
     * Prepare the controller name
     *
     * @param string  $name
     * @return string
     */
    protected function prepareName($name = '', $controller_prefix = '')
    {
        $name = ( $name != '') ? ucfirst($name) : 'User';
        
        if( substr($name,-10) == 'controller' )
        {
            $name = substr($name, 0, -10).'Controller';
        }
        else
        {
            $name .= 'Controller';
        }

        if (!empty($controller_prefix)) 
        {
            $name = ucfirst($controller_prefix) . "_" . $name;
        }

        return $name;
    }

    /**
     * Create the controller
     *
     * @param  string $name
     * @return bool
     */
    protected function createController( $name = '', $file_name = '' $controller_prefix = '', $restful = false )
    {
        $app = app();
        if (!empty($controller_prefix)) 
        {
            $file_name = strtolower($controller_prefix) . "/" . $file_name;
        }

        $controller_file = $this->laravel->path."/controllers/$file_name.php";
        $output = $app['view']->make('confide::generators.controller')
            ->with('name', $name)
            ->with('restful', $restful)
            ->render();

        if( ! file_exists( $controller_file ) )
        {
            $fs = fopen($controller_file, 'x');
            if ( $fs )
            {
                fwrite($fs, $output);
                fclose($fs);
                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

}

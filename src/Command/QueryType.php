<?php
	namespace ThreeDom\Pulse\Command;

	use ThreeDom\Pulse\Command;

	enum QueryType
	{
		case SELECT;
		case INSERT;
		case UPDATE;
		case DELETE;
		case PROCEDURE;
	}